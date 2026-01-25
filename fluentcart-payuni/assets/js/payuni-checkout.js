/**
 * PayUNi Checkout Handler
 *
 * FluentCart checkout 會用 fragments replace 掉 payment container 的 HTML，
 * 所以這裡一律用「重繪 UI」的方式確保畫面穩定、漂亮、可互動。
 *
 * UI/UX: Exaggerated Minimalism（乾淨留白 + 清楚的選取狀態）
 */
window.addEventListener('fluent_cart_load_payments_payuni', function (event) {
  const submitButton = window.fluentcart_checkout_vars?.submit_button;

  const container = document.querySelector(
    '.fluent-cart-checkout_embed_payment_container_payuni'
  );

  const payuniData = window.buygo_fc_payuni_data || {};

  const description =
    payuniData.description ||
    '使用 PayUNi（統一金流）付款。信用卡可站內刷卡並進行 3D 驗證，ATM/超商將直接取號顯示於收據頁。';

  const ACCENT = '#136196';

  function ensureStyles() {
    if (document.getElementById('buygo-payuni-ui-style-link')) {
      return;
    }

    const href =
      payuniData.css_url ||
      (function () {
        return '';
      })();

    if (!href) {
      return;
    }

    const link = document.createElement('link');
    link.id = 'buygo-payuni-ui-style-link';
    link.rel = 'stylesheet';
    link.href = href;
    document.head.appendChild(link);
  }

  function findCheckoutForm() {
    const methodInput = document.querySelector("input[name='_fct_pay_method']");
    if (methodInput) {
      const form = methodInput.closest('form');
      if (form) {
        return form;
      }
    }

    return document.querySelector('form');
  }

  function storageGet(key) {
    try {
      return window.sessionStorage.getItem(key) || '';
    } catch (e) {
      return '';
    }
  }

  function storageSet(key, val) {
    try {
      window.sessionStorage.setItem(key, val || '');
    } catch (e) {
      // ignore
    }
  }

  function ensureHidden(name, value) {
    const form = findCheckoutForm();
    if (!form) {
      return;
    }

    let hidden = form.querySelector(`input[name='${name}'][type='hidden']`);
    if (!hidden) {
      hidden = document.createElement('input');
      hidden.type = 'hidden';
      hidden.name = name;
      form.appendChild(hidden);
    }

    hidden.value = value || '';
  }

  function clearHiddenCardFields() {
    ensureHidden('payuni_card_number', '');
    ensureHidden('payuni_card_expiry', '');
    ensureHidden('payuni_card_cvc', '');
  }

  function clearHiddenAtmFields() {
    ensureHidden('payuni_bank_type', '');
  }

  function getSelectedPayType() {
    const stored = storageGet('buygo_fc_payuni_pay_type');
    return stored || 'credit';
  }

  function getSelectedBankType() {
    const stored = storageGet('buygo_fc_payuni_bank_type');
    return stored || '004';
  }

  function setSelectedBankType(val) {
    storageSet('buygo_fc_payuni_bank_type', val || '004');
    ensureHidden('payuni_bank_type', val || '004');
  }

  function setPayType(val) {
    const v = val || 'credit';

    storageSet('buygo_fc_payuni_pay_type', v);
    ensureHidden('payuni_payment_type', v);

    if (v !== 'credit') {
      clearHiddenCardFields();
    }

    if (v !== 'atm') {
      clearHiddenAtmFields();
    }
  }

  function createEl(tag, cls, text) {
    const el = document.createElement(tag);
    if (cls) {
      el.className = cls;
    }
    if (typeof text === 'string') {
      el.textContent = text;
    }
    return el;
  }

  function render() {
    if (!container) {
      return;
    }

    ensureStyles();

    const payType = getSelectedPayType();
    setPayType(payType);

    container.innerHTML = '';

    const root = createEl('div', 'buygo-payuni');
    root.style.setProperty('--buygo-payuni-accent', payuniData.accent || ACCENT);
    const card = createEl('div', 'card');

    const desc = createEl('p', 'muted', description);
    card.appendChild(desc);

    const methodSection = createEl('div', 'section');
    methodSection.appendChild(createEl('div', 'section-title', '付款方式'));

    const methods = createEl('div', 'methods');

    function methodCard(value, title, sub) {
      const label = createEl('label', 'method' + (payType === value ? ' selected' : ''));
      label.setAttribute('data-payuni-method', value);

      const left = createEl('div', 'left');
      const input = document.createElement('input');
      input.type = 'radio';
      input.name = 'payuni_payment_type';
      input.value = value;
      input.className = 'radio';
      input.checked = payType === value;

      const textWrap = createEl('div');
      textWrap.appendChild(createEl('div', 'label', title));
      textWrap.appendChild(createEl('div', 'desc small muted', sub));

      left.appendChild(input);
      left.appendChild(textWrap);

      label.appendChild(left);

      return label;
    }

    methods.appendChild(
      methodCard('credit', '信用卡', '站內填寫卡號後送出，會導向 3D 驗證頁，完成後回到收據頁。')
    );

    methods.appendChild(
      methodCard('atm', 'ATM 轉帳', '送出後會直接取號（轉帳帳號/期限），收據頁會顯示付款資訊。')
    );

    methods.appendChild(
      methodCard('cvs', '超商繳費', '送出後會直接取號（繳費代碼/期限），收據頁會顯示付款資訊。')
    );

    methodSection.appendChild(methods);
    card.appendChild(methodSection);

    if (payType === 'credit') {
      const section = createEl('div', 'section');
      section.appendChild(createEl('div', 'section-title', '信用卡資料'));

      const grid = createEl('div', 'grid');

      const f1 = createEl('div', 'field');
      const l1 = createEl('label', null, '卡號');
      l1.setAttribute('for', 'buygo_payuni_card_number');
      const i1 = document.createElement('input');
      i1.type = 'tel';
      i1.id = 'buygo_payuni_card_number';
      i1.autocomplete = 'cc-number';
      i1.inputMode = 'numeric';
      i1.placeholder = '4242 4242 4242 4242';
      f1.appendChild(l1);
      f1.appendChild(i1);
      grid.appendChild(f1);

      const row = createEl('div', 'grid-2');

      const f2 = createEl('div', 'field');
      const l2 = createEl('label', null, '有效期限（MM/YY）');
      l2.setAttribute('for', 'buygo_payuni_card_expiry');
      const i2 = document.createElement('input');
      i2.type = 'tel';
      i2.id = 'buygo_payuni_card_expiry';
      i2.autocomplete = 'cc-exp';
      i2.inputMode = 'numeric';
      i2.placeholder = '12/30';
      f2.appendChild(l2);
      f2.appendChild(i2);

      const f3 = createEl('div', 'field');
      const l3 = createEl('label', null, '安全碼（CVC）');
      l3.setAttribute('for', 'buygo_payuni_card_cvc');
      const i3 = document.createElement('input');
      i3.type = 'tel';
      i3.id = 'buygo_payuni_card_cvc';
      i3.autocomplete = 'cc-csc';
      i3.inputMode = 'numeric';
      i3.placeholder = '123';
      f3.appendChild(l3);
      f3.appendChild(i3);

      row.appendChild(f2);
      row.appendChild(f3);
      grid.appendChild(row);

      section.appendChild(grid);

      const hint = createEl(
        'div',
        'hint small muted',
        '請放心，我們將由「統一金流」加密，你的個人資料。'
      );
      section.appendChild(hint);

      card.appendChild(section);

      function syncCardToHidden() {
        const number = (i1.value || '').replace(/\s+/g, '');
        const expiry = (i2.value || '').replace(/\s+/g, '').replace(/[/-]/g, '');
        const cvc = (i3.value || '').replace(/\s+/g, '');

        ensureHidden('payuni_card_number', number);
        ensureHidden('payuni_card_expiry', expiry);
        ensureHidden('payuni_card_cvc', cvc);
      }

      i1.addEventListener('input', syncCardToHidden);
      i2.addEventListener('input', syncCardToHidden);
      i3.addEventListener('input', syncCardToHidden);
      syncCardToHidden();
    }

    if (payType === 'atm') {
      const section = createEl('div', 'section');
      section.appendChild(createEl('div', 'section-title', 'ATM 設定'));

      const grid = createEl('div', 'grid');

      const f1 = createEl('div', 'field');
      const l1 = createEl('label', null, '轉帳銀行');
      l1.setAttribute('for', 'buygo_payuni_bank_type');
      const s1 = document.createElement('select');
      s1.id = 'buygo_payuni_bank_type';

      const opts = [
        { value: '004', label: '台灣銀行（004）' },
        { value: '822', label: '中國信託（822）' },
        { value: '013', label: '國泰世華（013）' },
      ];

      const current = getSelectedBankType();
      opts.forEach(function (o) {
        const opt = document.createElement('option');
        opt.value = o.value;
        opt.textContent = o.label;
        if (o.value === current) {
          opt.selected = true;
        }
        s1.appendChild(opt);
      });

      f1.appendChild(l1);
      f1.appendChild(s1);
      grid.appendChild(f1);

      section.appendChild(grid);

      const hint = createEl(
        'div',
        'hint small muted',
        '提示：送出後不會跳轉頁面，收據頁會顯示轉帳帳號與繳費期限。'
      );
      section.appendChild(hint);

      card.appendChild(section);

      setSelectedBankType(current);
      s1.addEventListener('change', function () {
        setSelectedBankType(s1.value);
      });
    }

    root.appendChild(card);
    container.appendChild(root);
  }

  function markReady() {
    try {
      window.is_payuni_ready = true;
    } catch (e) {
      // ignore
    }
  }

  function enableCheckoutButton() {
    const txt = submitButton?.text || '送出訂單';

    if (event?.detail?.paymentLoader?.enableCheckoutButton) {
      event.detail.paymentLoader.enableCheckoutButton(txt);
      return;
    }

    if (
      window.fluent_cart_checkout_ui_service &&
      window.fluent_cart_checkout_ui_service.enableCheckoutButton
    ) {
      window.fluent_cart_checkout_ui_service.enableCheckoutButton();
      if (window.fluent_cart_checkout_ui_service.setCheckoutButtonText) {
        window.fluent_cart_checkout_ui_service.setCheckoutButtonText(txt);
      }
    }
  }

  render();
  markReady();
  enableCheckoutButton();

  if (!window.__buygoFcPayuniUiBound) {
    window.__buygoFcPayuniUiBound = true;

    document.addEventListener('change', function (e) {
      const t = e && e.target;
      if (!t) {
        return;
      }

      if (t.name === 'payuni_payment_type') {
        setPayType(t.value);
        render();
        markReady();
        enableCheckoutButton();
        return;
      }
    });
  }
});

