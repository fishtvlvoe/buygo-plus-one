/**
 * PayUNi Checkout Handler
 *
 * 讓 FluentCart 知道 PayUNi gateway 已就緒，並啟用結帳按鈕。
 */
window.addEventListener('fluent_cart_load_payments_payuni', function (event) {
  const submitButton = window.fluentcart_checkout_vars?.submit_button;

  const container = document.querySelector(
    '.fluent-cart-checkout_embed_payment_container_payuni'
  );

  const payuniData = window.buygo_fc_payuni_data || {};

  const description =
    payuniData.description || '使用 PayUNi（統一金流）付款，將導向至 PayUNi 付款頁完成付款。';

  if (container) {
    container.innerHTML = `<p>${description}</p>`;
  }

  window.is_payuni_ready = true;

  if (event.detail && event.detail.paymentLoader) {
    event.detail.paymentLoader.enableCheckoutButton(
      submitButton?.text || '送出訂單'
    );
  }
});

