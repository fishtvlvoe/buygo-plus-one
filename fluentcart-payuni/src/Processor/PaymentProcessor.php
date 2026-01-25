<?php

namespace BuyGoFluentCart\PayUNi\Processor;

use FluentCart\App\Helpers\Status;
use FluentCart\App\Helpers\StatusHelper;
use FluentCart\App\Models\Order;
use FluentCart\App\Models\OrderTransaction;
use FluentCart\App\Services\Payments\PaymentInstance;
use FluentCart\App\Services\Payments\PaymentHelper;

use BuyGoFluentCart\PayUNi\Gateway\PayUNiSettingsBase;

use BuyGoFluentCart\PayUNi\API\PayUNiAPI;
use BuyGoFluentCart\PayUNi\Services\PayUNiCryptoService;

use BuyGoFluentCart\PayUNi\Utils\Logger;

/**
 * PaymentProcessor
 *
 * 白話：把 FluentCart 的交易資料轉成 PayUNi 請求，並把必要識別存回 transaction meta。
 */
final class PaymentProcessor
{
    private PayUNiSettingsBase $settings;

    public function __construct(PayUNiSettingsBase $settings)
    {
        $this->settings = $settings;
    }

    public function processSinglePayment(PaymentInstance $paymentInstance): array
    {
        $transaction = $paymentInstance->transaction;
        $order = $paymentInstance->order;

        $trxHash = $transaction->uuid;

        // PayUNi MerTradeNo 有長度限制，不能直接用 32 位 uuid + timestamp。
        // 用「交易 id + 非數字分隔 + 短時間戳」來保持短且可回找。
        $merchantTradeNo = $this->generateMerTradeNo($transaction);

        $mode = $this->settings->getMode();

        $tradeAmt = $this->normalizeTradeAmount($transaction->total ?? 0);

        // 站內選擇付款方式（一次性）：credit / atm / cvs
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- checkout request
        $payType = !empty($_REQUEST['payuni_payment_type']) ? sanitize_text_field(wp_unslash($_REQUEST['payuni_payment_type'])) : '';

        Logger::info('Create PayUNi payment', [
            'transaction_uuid' => $trxHash,
            'merchant_trade_no' => $merchantTradeNo,
            'total' => $transaction->total ?? null,
            'trade_amt' => $tradeAmt,
            'mode' => $mode,
            'checkout_payment_type' => $payType,
        ]);

        $returnUrl = add_query_arg([
            'fct_payment_listener' => '1',
            'method' => 'payuni',
            'payuni_return' => '1',
            'trx_hash' => $trxHash,
        ], site_url('/'));

        $notifyUrl = add_query_arg([
            'fct_payment_listener' => '1',
            'method' => 'payuni',
        ], site_url('/'));

        // 一次性信用卡（站內刷卡 + 3D）：走 PayUNi credit API（跟 woomp 的做法一樣）
        // - 有卡號欄位就優先走 credit（避免「選信用卡卻還要去 PayUNi 頁面填卡」）
        // - 若前端沒送到卡號資料（例如 JS 被擋），退回 UPP credit 導轉，避免整個結帳壞掉
        if ($payType === 'credit') {
            $card = $this->getCardInputFromRequest();

            if ($card['number'] && $card['expiry'] && $card['cvc']) {
                return $this->processOnsiteCreditPayment(
                    $paymentInstance,
                    $merchantTradeNo,
                    $tradeAmt,
                    $mode,
                    $returnUrl,
                    $notifyUrl
                );
            }
        }

        // ATM / 超商：改成跟 woomp 一樣走幕後 API 直接取號，收據頁顯示繳費資訊
        if ($payType === 'atm' || $payType === 'cvs') {
            return $this->processBackendAtmOrCvsPayment(
                $paymentInstance,
                $merchantTradeNo,
                $tradeAmt,
                $mode,
                $returnUrl,
                $notifyUrl,
                $payType
            );
        }

        // UPP: 建立「整合式支付頁」(consumer redirect by POST form)
        // Required: MerID, MerTradeNo, TradeAmt, Timestamp
        // Recommended: UsrMail, ProdDesc, ReturnURL, NotifyURL
        $encryptInfo = [
            'MerID' => $this->settings->getMerId($mode),
            'MerTradeNo' => $merchantTradeNo,
            'TradeAmt' => $tradeAmt,
            'ExpireDate' => gmdate('Y-m-d', strtotime('+7 days')),
            'Timestamp' => time(),
        ];

        if (!empty($order->customer) && !empty($order->customer->email)) {
            $encryptInfo['UsrMail'] = (string) $order->customer->email;
        }

        $encryptInfo['ProdDesc'] = $this->buildProdDesc($order);
        $encryptInfo['ReturnURL'] = $returnUrl;
        $encryptInfo['NotifyURL'] = $notifyUrl;
        $encryptInfo['Lang'] = 'zh-tw';

        // 至少要指定一種支付方式，否則 PayUNi 可能直接回跳（看起來像「沒進入付款頁」）
        $encryptInfo['Credit'] = 0;
        $encryptInfo['ATM'] = 0;
        $encryptInfo['CVS'] = 0;

        if ($payType === 'atm') {
            $encryptInfo['ATM'] = 1;
        } elseif ($payType === 'cvs') {
            $encryptInfo['CVS'] = 1;
        } elseif ($payType === 'credit') {
            $encryptInfo['Credit'] = 1;
        } else {
            // fallback: 全開（維持舊行為）
            $encryptInfo['Credit'] = 1;
            $encryptInfo['ATM'] = 1;
            $encryptInfo['CVS'] = 1;
            $payType = 'all';
        }

        // Always log initiation (for debugging)
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log('[buygo-payuni][INIT] ' . wp_json_encode([
            'trx_hash' => $trxHash,
            'mode' => $mode,
            'endpoint' => (new PayUNiAPI($this->settings))->getEndpointUrl('upp', $mode),
            'MerTradeNo' => $merchantTradeNo,
            'TradeAmt' => $tradeAmt,
            'ReturnURL' => $returnUrl,
            'NotifyURL' => $notifyUrl,
        ]));

        $api = new PayUNiAPI($this->settings);
        $params = $api->buildParams($encryptInfo, 'upp', '2.0', $mode);

        if (empty($params['EncryptInfo'])) {
            return [
                'status' => 'failed',
                'message' => __('PayUNi encrypt failed. Please check HashKey/HashIV.', 'fluentcart-payuni'),
            ];
        }

        // Persist mapping for callbacks + refunds
        $transaction->meta = array_merge($transaction->meta ?? [], [
            'payuni' => array_merge(($transaction->meta['payuni'] ?? []), [
                'mode' => $mode,
                'trade_type' => 'upp',
                'mer_trade_no' => $merchantTradeNo,
                'trade_amt' => $tradeAmt,
                'return_url' => $returnUrl,
                'notify_url' => $notifyUrl,
                'checkout_payment_type' => $payType,
            ]),
        ]);
        $transaction->save();

        // Store form params temporarily (avoid putting EncryptInfo in URL)
        $tokenKey = 'buygo_fc_payuni_pay_' . $trxHash;
        set_transient($tokenKey, [
            'endpoint' => $api->getEndpointUrl('upp', $mode),
            'params' => [
                'MerID' => $params['MerID'],
                'Version' => $params['Version'],
                'EncryptInfo' => $params['EncryptInfo'],
                'HashInfo' => $params['HashInfo'],
            ],
        ], 30 * MINUTE_IN_SECONDS);

        // Auto-redirect helper:
        // FluentCart 有些結帳情境會先把使用者帶到收據頁（付款待處理），
        // 這個旗標讓我們在「那一次」收據頁載入時自動導去 PayUNi 付款頁。
        $autoRedirectKey = 'buygo_fc_payuni_autoredirect_' . $trxHash;
        set_transient($autoRedirectKey, true, 5 * MINUTE_IN_SECONDS);

        $payPageUrl = add_query_arg([
            'fluent-cart' => 'payuni_pay',
            'trx_hash' => $trxHash,
        ], home_url('/'));

        return [
            'status' => 'success',
            'nextAction' => 'redirect',
            'actionName' => 'custom',
            'message' => __('Redirecting to PayUNi...', 'fluentcart-payuni'),
            'data' => [
                'order' => [
                    'uuid' => $order->uuid,
                ],
                'transaction' => [
                    'uuid' => $transaction->uuid,
                ],
            ],
            'redirect_to' => $payPageUrl,
            'custom_payment_url' => PaymentHelper::getCustomPaymentLink($order->uuid),
        ];
    }

    public function confirmPaymentSuccess(OrderTransaction $transaction, array $payuniData, string $source = 'unknown'): void
    {
        if ($transaction->status === Status::TRANSACTION_SUCCEEDED) {
            return;
        }

        $order = Order::query()->where('id', $transaction->order_id)->first();
        if (!$order) {
            return;
        }

        $tradeNo = (string) ($payuniData['TradeNo'] ?? $payuniData['trade_no'] ?? '');
        $status = (string) ($payuniData['Status'] ?? $payuniData['status'] ?? '');
        $message = (string) ($payuniData['Message'] ?? $payuniData['message'] ?? '');

        $transaction->fill([
            'vendor_charge_id' => $tradeNo ?: ($transaction->vendor_charge_id ?? ''),
            'payment_method' => 'payuni',
            'status' => Status::TRANSACTION_SUCCEEDED,
            'payment_method_type' => 'PayUNi',
            'meta' => array_merge($transaction->meta ?? [], [
                'payuni' => array_merge(($transaction->meta['payuni'] ?? []), [
                    'trade_no' => $tradeNo,
                    'status' => $status,
                    'message' => $message,
                    'source' => $source,
                    'updated_at' => current_time('mysql'),
                    'raw' => $payuniData,
                ]),
            ]),
        ]);
        $transaction->save();

        fluent_cart_add_log(
            __('PayUNi Payment Confirmation', 'fluentcart-payuni'),
            sprintf(
                /* translators: 1: trade no, 2: source */
                __('Payment confirmed from PayUNi. TradeNo: %1$s (source: %2$s)', 'fluentcart-payuni'),
                $tradeNo ?: 'N/A',
                $source
            ),
            'info',
            [
                'module_name' => 'order',
                'module_id' => $order->id,
            ]
        );

        (new StatusHelper($order))->syncOrderStatuses($transaction);
    }

    public function confirmCreditPaymentSuccess(OrderTransaction $transaction, array $payuniData, string $source = 'unknown'): void
    {
        if ($transaction->status === Status::TRANSACTION_SUCCEEDED) {
            return;
        }

        $order = Order::query()->where('id', $transaction->order_id)->first();
        if (!$order) {
            return;
        }

        $tradeNo = (string) ($payuniData['TradeNo'] ?? '');
        $status = (string) ($payuniData['Status'] ?? '');
        $message = (string) ($payuniData['Message'] ?? '');
        $creditHash = (string) ($payuniData['CreditHash'] ?? '');
        $card4No = (string) ($payuniData['Card4No'] ?? '');
        $creditLife = (string) ($payuniData['CreditLife'] ?? '');

        $transaction->fill([
            'vendor_charge_id' => $tradeNo ?: ($transaction->vendor_charge_id ?? ''),
            'payment_method' => 'payuni',
            'status' => Status::TRANSACTION_SUCCEEDED,
            'payment_method_type' => 'PayUNi',
            'meta' => array_merge($transaction->meta ?? [], [
                'payuni' => array_merge(($transaction->meta['payuni'] ?? []), [
                    'trade_type' => 'credit',
                    'trade_no' => $tradeNo,
                    'status' => $status,
                    'message' => $message,
                    'source' => $source,
                    'credit' => [
                        'credit_hash' => $creditHash,
                        'card_4no' => $card4No,
                        'credit_life' => $creditLife,
                    ],
                    'updated_at' => current_time('mysql'),
                    'raw' => $payuniData,
                ]),
            ]),
        ]);
        $transaction->save();

        (new StatusHelper($order))->syncOrderStatuses($transaction);
    }

    public function processFailedPayment(OrderTransaction $transaction, array $reasonData, string $source = 'unknown'): void
    {
        $order = Order::query()->where('id', $transaction->order_id)->first();
        if (!$order) {
            return;
        }

        $status = (string) ($reasonData['Status'] ?? $reasonData['status'] ?? '');
        $message = (string) ($reasonData['Message'] ?? $reasonData['message'] ?? ($reasonData['reason'] ?? ''));

        $transaction->meta = array_merge($transaction->meta ?? [], [
            'payuni' => array_merge(($transaction->meta['payuni'] ?? []), [
                'failed' => true,
                'status' => $status,
                'message' => $message,
                'source' => $source,
                'updated_at' => current_time('mysql'),
                'raw' => $reasonData,
            ]),
        ]);
        $transaction->save();

        fluent_cart_add_log(
            __('PayUNi Payment Failed', 'fluentcart-payuni'),
            sprintf(
                /* translators: 1: message, 2: source */
                __('Payment failed. %1$s (source: %2$s)', 'fluentcart-payuni'),
                $message ?: 'Unknown',
                $source
            ),
            'error',
            [
                'module_name' => 'order',
                'module_id' => $order->id,
            ]
        );
    }

    private function processOnsiteCreditPayment(
        PaymentInstance $paymentInstance,
        string $merchantTradeNo,
        int $tradeAmt,
        string $mode,
        string $returnUrl,
        string $notifyUrl
    ): array {
        $transaction = $paymentInstance->transaction;
        $order = $paymentInstance->order;

        $trxHash = (string) $transaction->uuid;

        $card = $this->getCardInputFromRequest();
        if (!$card['number'] || !$card['expiry'] || !$card['cvc']) {
            return [
                'status' => 'failed',
                'message' => __('請先填寫信用卡卡號、有效期限與安全碼。', 'fluentcart-payuni'),
            ];
        }

        $usrMail = '';
        try {
            if (!empty($order->customer) && !empty($order->customer->email)) {
                $usrMail = (string) $order->customer->email;
            }
        } catch (\Throwable $e) {
            $usrMail = '';
        }

        if (!$usrMail) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- checkout request
            $usrMail = !empty($_REQUEST['billing_email']) ? sanitize_email(wp_unslash($_REQUEST['billing_email'])) : '';
        }

        $encryptInfo = [
            'MerID' => $this->settings->getMerId($mode),
            'MerTradeNo' => $merchantTradeNo,
            'TradeAmt' => $tradeAmt,
            'Timestamp' => time(),
            'ProdDesc' => $this->buildProdDesc($order),
            'ReturnURL' => $returnUrl,
            'NotifyURL' => $notifyUrl,
            'CardNo' => $card['number'],
            'CardExpired' => $card['expiry'],
            'CardCVC' => $card['cvc'],
            'Lang' => 'zh-tw',
            // 站內刷卡必開 3D（跟訂閱初次付款一致）
            'API3D' => 1,
        ];

        if ($usrMail) {
            $encryptInfo['UsrMail'] = $usrMail;
            // 這個欄位在 woomp 也用 email 當識別；一次性付款不一定需要存 token，
            // 但填上可避免某些情境 PayUNi 端要求 token 憑證。
            $encryptInfo['CreditToken'] = $usrMail;
        }

        // Persist mapping for callbacks
        $transaction->meta = array_merge($transaction->meta ?? [], [
            'payuni' => array_merge(($transaction->meta['payuni'] ?? []), [
                'mode' => $mode,
                'trade_type' => 'credit',
                'mer_trade_no' => $merchantTradeNo,
                'trade_amt' => $tradeAmt,
                'return_url' => $returnUrl,
                'notify_url' => $notifyUrl,
                'checkout_payment_type' => 'credit',
                'credit_init' => [
                    'has_card_input' => true,
                ],
            ]),
        ]);
        $transaction->save();

        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log('[buygo-payuni][CREDIT_INIT] ' . wp_json_encode([
            'trx_hash' => $trxHash,
            'mode' => $mode,
            'endpoint' => (new PayUNiAPI($this->settings))->getEndpointUrl('credit', $mode),
            'MerTradeNo' => $merchantTradeNo,
            'TradeAmt' => $tradeAmt,
            'ReturnURL' => $returnUrl,
        ]));

        $api = new PayUNiAPI($this->settings);
        $resp = $api->post('credit', $encryptInfo, '1.0', $mode);

        if (is_wp_error($resp)) {
            return [
                'status' => 'failed',
                'message' => $resp->get_error_message(),
            ];
        }

        if (empty($resp['EncryptInfo']) || empty($resp['HashInfo'])) {
            return [
                'status' => 'failed',
                'message' => __('PayUNi 回傳格式不正確（缺少 EncryptInfo/HashInfo）。', 'fluentcart-payuni'),
            ];
        }

        $crypto = new PayUNiCryptoService($this->settings);
        if (!$crypto->verifyHashInfo((string) $resp['EncryptInfo'], (string) $resp['HashInfo'], $mode)) {
            return [
                'status' => 'failed',
                'message' => __('PayUNi 回傳 HashInfo 驗證失敗。', 'fluentcart-payuni'),
            ];
        }

        $decrypted = $crypto->decryptInfo((string) $resp['EncryptInfo'], $mode);
        $status = (string) ($decrypted['Status'] ?? '');
        $message = (string) ($decrypted['Message'] ?? '');

        $transaction->meta = array_merge($transaction->meta ?? [], [
            'payuni' => array_merge(($transaction->meta['payuni'] ?? []), [
                'credit_init' => array_merge(($transaction->meta['payuni']['credit_init'] ?? []), [
                    'status' => $status,
                    'message' => $message,
                    'raw' => $decrypted,
                ]),
            ]),
        ]);
        $transaction->save();

        if ($status !== 'SUCCESS') {
            return [
                'status' => 'failed',
                'message' => $message ?: __('信用卡付款建立失敗。', 'fluentcart-payuni'),
            ];
        }

        $nextUrl = (string) ($decrypted['URL'] ?? '');
        if ($nextUrl) {
            return [
                'status' => 'success',
                'nextAction' => 'redirect',
                'actionName' => 'custom',
                'message' => __('正在前往 3D 驗證頁面...', 'fluentcart-payuni'),
                'data' => [
                    'order' => [
                        'uuid' => $order->uuid,
                    ],
                    'transaction' => [
                        'uuid' => $transaction->uuid,
                    ],
                ],
                'redirect_to' => $nextUrl,
                'custom_payment_url' => PaymentHelper::getCustomPaymentLink($order->uuid),
            ];
        }

        // 少數情況：沒有 3D 直接成功
        $this->confirmCreditPaymentSuccess($transaction, $decrypted, 'credit_init');

        return [
            'status' => 'success',
            'nextAction' => 'success',
            'message' => __('付款成功', 'fluentcart-payuni'),
            'data' => [
                'order' => [
                    'uuid' => $order->uuid,
                ],
                'transaction' => [
                    'uuid' => $transaction->uuid,
                ],
            ],
        ];
    }

    private function processBackendAtmOrCvsPayment(
        PaymentInstance $paymentInstance,
        string $merchantTradeNo,
        int $tradeAmt,
        string $mode,
        string $returnUrl,
        string $notifyUrl,
        string $payType
    ): array {
        $transaction = $paymentInstance->transaction;
        $order = $paymentInstance->order;

        $trxHash = (string) $transaction->uuid;

        $encryptInfo = [
            'MerID' => $this->settings->getMerId($mode),
            'MerTradeNo' => $merchantTradeNo,
            'TradeAmt' => $tradeAmt,
            'ExpireDate' => gmdate('Y-m-d', strtotime('+7 days')),
            'Timestamp' => time(),
            'UsrMail' => '',
            'ProdDesc' => $this->buildProdDesc($order),
            'ReturnURL' => $returnUrl,
            'NotifyURL' => $notifyUrl,
            'Lang' => 'zh-tw',
        ];

        if (!empty($order->customer) && !empty($order->customer->email)) {
            $encryptInfo['UsrMail'] = (string) $order->customer->email;
        }

        // ATM 可以帶 BankType（woomp 會讓客人選銀行）
        if ($payType === 'atm') {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- checkout request
            $bankType = !empty($_REQUEST['payuni_bank_type']) ? sanitize_text_field(wp_unslash($_REQUEST['payuni_bank_type'])) : '';
            if ($bankType) {
                $encryptInfo['BankType'] = $bankType;
            }
        }

        $transaction->meta = array_merge($transaction->meta ?? [], [
            'payuni' => array_merge(($transaction->meta['payuni'] ?? []), [
                'mode' => $mode,
                'trade_type' => $payType,
                'mer_trade_no' => $merchantTradeNo,
                'trade_amt' => $tradeAmt,
                'return_url' => $returnUrl,
                'notify_url' => $notifyUrl,
                'checkout_payment_type' => $payType,
            ]),
        ]);
        $transaction->save();

        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log('[buygo-payuni][' . strtoupper($payType) . '_INIT] ' . wp_json_encode([
            'trx_hash' => $trxHash,
            'mode' => $mode,
            'endpoint' => (new PayUNiAPI($this->settings))->getEndpointUrl($payType, $mode),
            'MerTradeNo' => $merchantTradeNo,
            'TradeAmt' => $tradeAmt,
        ]));

        $api = new PayUNiAPI($this->settings);
        $resp = $api->post($payType, $encryptInfo, '1.0', $mode);

        if (is_wp_error($resp)) {
            return [
                'status' => 'failed',
                'message' => $resp->get_error_message(),
            ];
        }

        if (empty($resp['EncryptInfo']) || empty($resp['HashInfo'])) {
            return [
                'status' => 'failed',
                'message' => __('PayUNi 回傳格式不正確（缺少 EncryptInfo/HashInfo）。', 'fluentcart-payuni'),
            ];
        }

        $crypto = new PayUNiCryptoService($this->settings);
        if (!$crypto->verifyHashInfo((string) $resp['EncryptInfo'], (string) $resp['HashInfo'], $mode)) {
            return [
                'status' => 'failed',
                'message' => __('PayUNi 回傳 HashInfo 驗證失敗。', 'fluentcart-payuni'),
            ];
        }

        $decrypted = $crypto->decryptInfo((string) $resp['EncryptInfo'], $mode);
        $status = (string) ($decrypted['Status'] ?? '');
        $message = (string) ($decrypted['Message'] ?? '');

        if ($status !== 'SUCCESS') {
            return [
                'status' => 'failed',
                'message' => $message ?: __('取號失敗，請稍後再試。', 'fluentcart-payuni'),
            ];
        }

        // 取號成功：把繳費資訊存到 transaction meta，收據頁顯示
        $paymentTypeCode = ($payType === 'atm') ? '2' : '3';
        $transaction->meta = array_merge($transaction->meta ?? [], [
            'payuni' => array_merge(($transaction->meta['payuni'] ?? []), [
                'pending' => [
                    'payment_type' => $paymentTypeCode,
                    'trade_no' => (string) ($decrypted['TradeNo'] ?? ''),
                    'trade_amt' => (string) ($decrypted['TradeAmt'] ?? ''),
                    'message' => $message,
                    'bank_type' => (string) ($decrypted['BankType'] ?? ''),
                    'pay_no' => (string) ($decrypted['PayNo'] ?? ''),
                    'expire_date' => (string) ($decrypted['ExpireDate'] ?? ''),
                    'raw' => $decrypted,
                ],
            ]),
        ]);
        $transaction->save();

        $receiptUrl = add_query_arg([
            'trx_hash' => $trxHash,
            'fct_redirect' => 'yes',
            'payuni_return' => '1',
        ], $transaction->getReceiptPageUrl(true));

        return [
            'status' => 'success',
            'nextAction' => 'redirect',
            'actionName' => 'custom',
            'message' => __('已取得繳費資訊，正在前往收據頁...', 'fluentcart-payuni'),
            'data' => [
                'order' => [
                    'uuid' => $order->uuid,
                ],
                'transaction' => [
                    'uuid' => $transaction->uuid,
                ],
            ],
            'redirect_to' => $receiptUrl,
            'custom_payment_url' => PaymentHelper::getCustomPaymentLink($order->uuid),
        ];
    }

    private function normalizeTradeAmount($rawAmount): int
    {
        // FluentCart most commonly stores amounts in cents (integer).
        $amountInt = is_numeric($rawAmount) ? (int) $rawAmount : 0;
        $tradeAmt = (int) round($amountInt / 100);

        // Fallback: if cents-division becomes 0 but original is positive, assume already in "元"
        if ($tradeAmt < 1 && $amountInt >= 1) {
            $tradeAmt = $amountInt;
        }

        // PayUNi requires positive integer
        if ($tradeAmt < 1) {
            $tradeAmt = 1;
        }

        return $tradeAmt;
    }

    private function buildProdDesc($order): string
    {
        try {
            $items = $order->order_items ?? [];
            if (is_array($items) && count($items) > 0) {
                $first = $items[0];
                $title = (string) ($first->title ?? $first->post_title ?? '');
                if ($title) {
                    return $title;
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return (string) get_bloginfo('name');
    }

    private function generateMerTradeNo($transaction): string
    {
        $id = (int) ($transaction->id ?? 0);
        if ($id < 1) {
            // fallback: 取 uuid 前 10 碼（仍然短）
            $idPart = substr((string) ($transaction->uuid ?? ''), 0, 10);
            $idPart = preg_replace('/[^a-zA-Z0-9]/', '', (string) $idPart);
            $idPart = $idPart ?: (string) time();
            return 'T' . $idPart;
        }

        $timePart = base_convert((string) time(), 10, 36);
        $randPart = substr(md5(wp_generate_password(12, false, false)), 0, 2);

        // Example: "123Akw3f9zq" (digit id + 'A' + base36 time + 2 chars)
        return $id . 'A' . $timePart . $randPart;
    }

    /**
     * 從 checkout request 讀取信用卡欄位（只用於當次交易，不保存）。
     *
     * @return array{number:string,expiry:string,cvc:string}
     */
    private function getCardInputFromRequest(): array
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- checkout request
        $number = !empty($_REQUEST['payuni_card_number']) ? sanitize_text_field(wp_unslash($_REQUEST['payuni_card_number'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- checkout request
        $expiry = !empty($_REQUEST['payuni_card_expiry']) ? sanitize_text_field(wp_unslash($_REQUEST['payuni_card_expiry'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- checkout request
        $cvc = !empty($_REQUEST['payuni_card_cvc']) ? sanitize_text_field(wp_unslash($_REQUEST['payuni_card_cvc'])) : '';

        $number = preg_replace('/\s+/', '', (string) $number);
        $expiry = preg_replace('/\s+/', '', (string) $expiry);
        $cvc = preg_replace('/\s+/', '', (string) $cvc);

        $expiry = str_replace(['/', '-'], '', (string) $expiry);

        return [
            'number' => (string) $number,
            'expiry' => (string) $expiry,
            'cvc' => (string) $cvc,
        ];
    }
}

