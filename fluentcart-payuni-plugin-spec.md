▋ 文件目的

這份文件是要直接丟給 Claude Code，用來產出一個「FluentCart 第三方金流外掛（PayUNi）」的第一版可用實作。

這不是任務清單，而是「要做出來的東西長什麼樣、怎麼跟 FluentCart 接、怎麼跟 PayUNi 接」的規格說明。


▋ 一句話總結（你要做什麼）

做一個獨立 WordPress 外掛，讓 FluentCart 多一個付款方式 `payuni統一金流`。

使用者結帳選 PayUNi 時，外掛把訂單資料送去 PayUNi，PayUNi 回來（ReturnURL 或 NotifyURL/Webhook）時，外掛把 FluentCart 的交易狀態改成成功或失敗，並支援退款。


▋ 參考對象（照抄整合方式的來源）

請以這個外掛的「接法」為模板（不是抄它的金流邏輯，而是抄它跟 FluentCart 的整合點與分層方式）。

`/Users/fishtv/Local Sites/buygo/app/public/wp-content/plugins/wpkj-alipay-gateway-for-fluentcart/`

它的關鍵做法是：

• 在 `fluent_cart/register_payment_methods` 註冊一個 gateway

• gateway 繼承 FluentCart 的 `AbstractPaymentGateway`

• 付款從 `makePaymentFromPaymentInstance()` 進來

• Webhook 從 `handleIPN()` 進來

• ReturnURL 用 `boot()` 在看到特徵 querystring 時立即處理（加速更新狀態，不用等 webhook）


▋ 這個外掛跟 FluentCart 的接點（一定要照這個形狀）

外掛主檔必須在 FluentCart 載入後，註冊 payment method。

註冊時使用 FluentCart 的 hook：`fluent_cart/register_payment_methods`。

註冊時的 slug 建議用 `payuni`（對齊 `$transaction->payment_method` 的判斷）。


▋ 你要做的 gateway 類別（PayUNiGateway）

檔案建議：`src/Gateway/PayUNiGateway.php`

類別必須繼承：`FluentCart\App\Modules\PaymentMethods\Core\AbstractPaymentGateway`

這個類別只做「跟 FluentCart 溝通」，不要把 API 請求細節塞進來。

它至少要提供：

• `meta(): array` 回傳標題、slug、route、logo、描述、是否啟用、支援功能

• `fields(): array` 回傳後台設定欄位（測試/正式、MerID、HashKey、HashIV、付款模式、debug）

• `makePaymentFromPaymentInstance(PaymentInstance $paymentInstance)` 作為付款入口，呼叫 Processor 產生 PayUNi 請求並回傳 FluentCart 需要的回應格式

• `handleIPN()` 作為 webhook 入口，呼叫 NotifyHandler 處理並輸出成功/失敗字串給 PayUNi

• `boot()`（建議要）用來處理 ReturnURL 的加速更新：看到 querystring 有交易識別就直接啟動 ReturnHandler


▋ FluentCart 會給你什麼（PaymentInstance 裡你最該拿的三個東西）

你會拿到 `$paymentInstance`，裡面至少會有：

• `$paymentInstance->order` 訂單

• `$paymentInstance->transaction` 交易（最重要）

• `$paymentInstance->subscription`（如果你有做訂閱才會用到，第一版可以先不做）

你做 PayUNi 最重要的識別鍵就是 `transaction uuid`，你要把它當成「所有回來的 webhook / return 都要能找到它」的核心。


▋ 金額與單位（這裡很容易炸）

請不要假設 FluentCart 金額一定是「元」。

你必須在實作時確認 `$transaction->total` 的實際單位後，再決定送給 PayUNi 的 TradeAmt 要不要乘/除 100。

如果你不確定，就先在 log 裡把 `$transaction->total`、訂單幣別、以及結帳頁顯示金額一起印出來對照。


▋ 跟 PayUNi API 溝通的方式（兩條路，先選一條就好）

你可以走其中一條就能完成第一版。

• 路線 A（建議先走）：沿用 woomp 已驗證的加解密邏輯，自己打 PayUNi API

好處是你不用引入 composer 依賴，也比較不會在 WordPress hosting 環境踩到 autoload 問題，而且你已經有一份跑過的參考實作。

• 路線 B（可選）：使用 PAYUNi 官方 PHP SDK 當底層

好處是它把 `UniversalTrade($encryptInfo, $mode, $version)` 跟 `ResultProcess($requestData)` 這兩個核心入口包好了，你的程式碼會比較短。

但第一版不要押寶這條路線，除非你確定部署環境允許 composer 或你願意把 SDK 以 vendor 形式打包進外掛。

SDK 參考：`https://github.com/payuni/PHP_SDK`

API docs：`https://docs.payuni.com.tw/web/#/7/24`


▋ 分層設計（照 Alipay 外掛的切法做，換成 PayUNi）

請把程式拆成四層，這樣 Claude Code 比較不會把所有東西混在一起。

• Gateway 層：只負責跟 FluentCart 對接（註冊、fields、付款入口、IPN 入口）

• Processor 層：只負責付款流程（把 PaymentInstance 變成 PayUNi 請求、把結果落地到 transaction/order）

• API/Service 層：只負責呼叫 PayUNi 與加解密/簽章（不要碰 FluentCart 物件）

• Webhook/Return 層：只負責驗證回傳、去重、防呆、找到 transaction、交給 Processor 更新狀態


▋ 建議檔案結構（第一版）

外掛根目錄名稱建議：`fluentcart-payuni/`

檔案建議長這樣：

• `fluentcart-payuni.php` 外掛入口，做 dependency check、autoload、註冊 payment method、註冊額外 handler

• `src/Gateway/PayUNiGateway.php` FluentCart gateway

• `src/Gateway/PayUNiSettingsBase.php` 後台設定讀寫（測試/正式、MerID、HashKey、HashIV、付款模式、debug）

• `src/Processor/PaymentProcessor.php` 付款發起與交易落地（存 meta、回傳 redirect 或顯示資訊）

• `src/Processor/RefundProcessor.php` 退款（第一版只要支援一般退款，不用做自動退款）

• `src/Webhook/NotifyHandler.php` PayUNi 的 webhook（NotifyURL）處理

• `src/Webhook/ReturnHandler.php` PayUNi 的 return（ReturnURL）處理

• `src/Services/PayUNiCryptoService.php` 加解密與 hash（純函式、不要依賴 WC/FluentCart）

• `src/API/PayUNiAPI.php` 封裝呼叫 PayUNi endpoint（或在這裡做 SDK adapter）

• `src/Utils/Logger.php` 記錄 log（要能開關）


▋ 付款資料如何從 FluentCart 拼成 PayUNi 請求（重點是可追蹤與可回找）

你在 `makePaymentFromPaymentInstance()` 進來後，請以 transaction 為核心。

你要生成一個 PayUNi 要求的「商店端交易編號」，並且必須保證每次付款嘗試都不會撞單。

最簡單的做法是使用 transaction uuid 當核心，再加一個時間戳或短亂數，避免使用者重試時撞到同一筆單號。

然後你必須把你送出去的「商店端交易編號」存回 `$transaction->meta`，因為你後面要做：

• webhook 對帳

• return 加速更新

• 交易查詢（如果你有做）

• 退款（一定需要）

如果你不存，你後面就會卡在「我現在該用哪個單號」這種最常見的金流外掛災難。


▋ NotifyURL（Webhook/IPN）一定要做的兩個保護（照 Alipay 外掛抄）

• 去重（防重送/防重放）

如果 PayUNi webhook 有 `notify_id` 類似欄位就用它。

如果沒有，就用「商店端交易編號 + 狀態 + 金額」做一個 hash key，存 transient 一段時間。

重複收到時直接回成功字串，避免同一筆交易被更新兩次。

• 只處理屬於自己的交易

你找到 transaction 後，要先檢查 `$transaction->payment_method === 'payuni'`。

不是就直接忽略，避免同一個 listener 被別的金流誤打造成污染。


▋ ReturnURL（建議要做，因為使用者體感差很多）

ReturnURL 不要只靠「使用者回跳」就當成功，因為使用者可能關頁面或網路斷線。

ReturnURL 的角色是加速更新，不是唯一真相。

建議做法是：

看到 `trx_hash`（transaction uuid）就啟動 ReturnHandler，然後用「查交易」或「解密回傳」得到狀態，再更新 FluentCart 交易狀態。

Webhook 還是保險，最後狀態以 webhook 為準。


▋ FluentCart 交易狀態更新的原則（用 Processor 做收斂）

你要把「更新狀態」集中在 Processor 的幾個函式，避免 webhook/return/查詢各自亂改。

建議 Processor 至少有這兩個入口（名字可調，但概念要一樣）：

• `confirmPaymentSuccess(OrderTransaction $transaction, array $payuniData, string $source)` 來源可能是 webhook 或 return

• `processFailedPayment(OrderTransaction $transaction, array $reasonData, string $source)`

更新時要同時做到：

• transaction 狀態更新成 succeeded 或 failed

• 把 PayUNi 的交易編號與關鍵資料存進 transaction meta（方便查詢與退款）

• 必要的 order 狀態更新（以 FluentCart 的流程為準，不要自己亂發事件）


▋ 退款（第一版最小支援）

第一版退款只需要做到「後台按退款，能呼叫 PayUNi 退款 API，成功後 FluentCart 記錄一筆 refund transaction」。

重點是你要能找到原交易的 PayUNi 交易編號或商店端交易編號，所以前面存 meta 這件事是硬前提。

退款時要做最基本的重複退款保護：同一筆原交易如果已經存在 refund transaction，就不要再跑一次。


▋ 外掛入口檔（主檔）必須做的事（文字規格，不是清單）

主檔要檢查 FluentCart 是否存在（例如檢查 `FluentCart\App\Modules\PaymentMethods\Core\GatewayManager`）。

主檔要在 FluentCart 可用時，註冊 payment method（`fluent_cart/register_payment_methods`）。

主檔要提供 autoloader（可以像參考外掛那樣用 `spl_autoload_register`，或用 composer，但第一版建議先用 spl_autoload_register）。


▋ 你不應該在第一版就做的東西（避免範圍爆炸）

不要在第一版做「對外 REST API 建立付款單」這種功能。

因為那會變成另一個入口，需要權限控管、nonce/token、CORS、風控，會讓金流外掛的攻擊面變大。

先把 FluentCart 結帳付款這條路跑通，第二版再談外部 API。


▋ 實作時你可以直接參考的既有文件（本機）

PayUNi 整合規劃與流程（已整理成 md）：

`/Users/fishtv/Desktop/VT工作流/_Archive/舊項目/claude-code-buygo-pluse-one/02_開發任務/PayUNi-FluentCart-整合開發計畫.md`

`/Users/fishtv/Desktop/VT工作流/_Archive/舊項目/claude-code-buygo-pluse-one/02_開發任務/PayUNi-FluentCart-功能架構圖解.md`

`/Users/fishtv/Desktop/VT工作流/_Archive/舊項目/doc/老魚資料庫/05_外掛開發文件/payuni.com.tw_doc/`

woomp 的 PayUNi 既有程式碼備份（可拿來抽加解密）：

`/Users/fishtv/Desktop/VT工作流/_Archive/舊項目/doc/老魚資料庫/04_歸檔區/備份檔案/主機buygo備份/app/public/wp-content/plugins/woomp/includes/payuni/`


▋ 成品標準（你要怎麼判斷第一版有沒有完成）

你在 FluentCart 後台能看到 payuni 付款方式，能開關，能設定測試/正式的憑證。

前台結帳選 payuni，能完成至少一種付款（建議先做信用卡或整合支付頁）。

付款完成後，不管使用者回不回跳，最後都能靠 NotifyURL 把交易狀態更新成成功。

後台對已成功付款的訂單可以按退款，退款成功後 FluentCart 有記錄 refund transaction。

