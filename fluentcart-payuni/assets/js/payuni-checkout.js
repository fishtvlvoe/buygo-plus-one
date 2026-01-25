/**
 * PayUNi Checkout Handler
 *
 * FluentCart checkout 會用 fragments replace 掉 payment container 的 HTML，
 * 所以這裡一律用「重繪 UI」的方式確保畫面穩定、漂亮、可互動。
 *
 * UI/UX: Exaggerated Minimalism（乾淨留白 + 清楚的選取狀態）
 */
(function () {
  if (window.__buygoFcPayuniCheckoutUiLoaded) {
    return;
  }

  window.__buygoFcPayuniCheckoutUiLoaded = true;

  const ACCENT = '#136196';

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

  function ensureStyles(payuniData) {
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

  function findContainer(route) {
    return (
      document.querySelector(`.fluent-cart-checkout_embed_payment_container_${route}`) ||
      document.querySelector(
        `.fluent-cart-checkout_embed_payment_container_${route.replace(/_/g, '-')}`
      )
    );
  }

  function bindGatewayUi(route, options) {
    const opts = options || {};
    const allowPayTypeSelect = opts.allowPayTypeSelect !== false;
    const allowedPayTypes = Array.isArray(opts.allowedPayTypes) ? opts.allowedPayTypes : null;
    const displayPayTypes = Array.isArray(opts.displayPayTypes) ? opts.displayPayTypes : null;
    const data =
      (route === 'payuni_subscription'
        ? window.buygo_fc_payuni_subscription_data
        : window.buygo_fc_payuni_data) ||
      window.buygo_fc_payuni_data ||
      {};

    const defaultDescription =
      route === 'payuni_subscription'
        ? '使用 PayUNi 信用卡定期定額付款（初次需 3D 驗證）。'
        : '使用 PayUNi（統一金流）付款。信用卡可站內刷卡並進行 3D 驗證，ATM/超商將直接取號顯示於收據頁。';

    const description = data.description || defaultDescription;
    const storagePrefix = route === 'payuni' ? 'payuni' : 'payuni_subscription';

    function clearHiddenCardFields() {
      ensureHidden('payuni_card_number', '');
      ensureHidden('payuni_card_expiry', '');
      ensureHidden('payuni_card_cvc', '');
    }

    function clearHiddenAtmFields() {
      ensureHidden('payuni_bank_type', '');
    }

    function getSelectedPayType() {
      if (!allowPayTypeSelect) {
        return 'credit';
      }

      const stored = storageGet(`buygo_fc_${storagePrefix}_pay_type`);
      const v = stored || 'credit';
      if (allowedPayTypes && !allowedPayTypes.includes(v)) {
        return allowedPayTypes[0] || 'credit';
      }
      return v;
    }

    function getSelectedBankType() {
      const stored = storageGet(`buygo_fc_${storagePrefix}_bank_type`);
      return stored || '004';
    }

    function setSelectedBankType(val) {
      const v = val || '004';
      storageSet(`buygo_fc_${storagePrefix}_bank_type`, v);
      ensureHidden('payuni_bank_type', v);
    }

    function setPayType(val) {
      const v = val || 'credit';

      if (allowedPayTypes && !allowedPayTypes.includes(v)) {
        return;
      }

      if (allowPayTypeSelect) {
        storageSet(`buygo_fc_${storagePrefix}_pay_type`, v);
        ensureHidden('payuni_payment_type', v);
      }

      if (v !== 'credit') {
        clearHiddenCardFields();
      }

      if (v !== 'atm') {
        clearHiddenAtmFields();
      }
    }

    function render(container) {
      if (!container) {
        return;
      }

      ensureStyles(data);

      const payType = getSelectedPayType();
      setPayType(payType);

      container.innerHTML = '';

      const root = createEl('div', 'buygo-payuni');
      root.style.setProperty('--buygo-payuni-accent', data.accent || ACCENT);
      const card = createEl('div', 'card');

      const desc = createEl('p', 'muted', description);
      card.appendChild(desc);

      const methodSection = createEl('div', 'section');
      methodSection.appendChild(createEl('div', 'section-title', '付款方式'));
      const methods = createEl('div', 'methods');

      function methodCard(value, title, sub, config) {
        const cfg = config || {};
        const selected = (cfg.selectedValue || payType) === value;
        const label = createEl('label', 'method' + (selected ? ' selected' : ''));
        label.setAttribute('data-payuni-method', value);

        const left = createEl('div', 'left');
        const input = document.createElement('input');
        input.type = 'radio';
        input.name = cfg.inputName || 'payuni_payment_type';
        input.value = value;
        input.className = 'radio';
        input.checked = selected;
        if (cfg.disabled) {
          input.disabled = true;
        }

        const textWrap = createEl('div');
        textWrap.appendChild(createEl('div', 'label', title));
        textWrap.appendChild(createEl('div', 'desc small muted', sub));

        left.appendChild(input);
        left.appendChild(textWrap);

        label.appendChild(left);

        return label;
      }

      const payTypeCards = [
        {
          value: 'credit',
          title: '信用卡',
          sub:
            route === 'payuni_subscription'
              ? '初次付款會導向 3D 驗證頁，完成後回到收據頁，後續將由系統自動續扣。'
              : '站內填寫卡號後送出，會導向 3D 驗證頁，完成後回到收據頁。',
        },
        {
          value: 'atm',
          title: 'ATM 轉帳',
          sub: '送出後會直接取號（轉帳帳號/期限），收據頁會顯示付款資訊。',
        },
        {
          value: 'cvs',
          title: '超商繳費',
          sub: '送出後會直接取號（繳費代碼/期限），收據頁會顯示付款資訊。',
        },
      ];

      payTypeCards.forEach(function (c) {
        if (displayPayTypes && !displayPayTypes.includes(c.value)) {
          return;
        }

        methods.appendChild(
          methodCard(c.value, c.title, c.sub, {
            disabled: allowedPayTypes ? !allowedPayTypes.includes(c.value) : false,
          })
        );
      });

      methodSection.appendChild(methods);
      card.appendChild(methodSection);

      if (payType === 'credit') {
        const section = createEl('div', 'section');
        section.appendChild(createEl('div', 'section-title', '信用卡資料'));

        const grid = createEl('div', 'grid');

        const f1 = createEl('div', 'field');
        const l1 = createEl('label', null, '卡號');
        l1.setAttribute('for', `buygo_${route}_card_number`);
        const i1 = document.createElement('input');
        i1.type = 'tel';
        i1.id = `buygo_${route}_card_number`;
        i1.autocomplete = 'cc-number';
        i1.inputMode = 'numeric';
        i1.placeholder = '4242 4242 4242 4242';
        f1.appendChild(l1);
        f1.appendChild(i1);
        grid.appendChild(f1);

        const row = createEl('div', 'grid-2');

        const f2 = createEl('div', 'field');
        const l2 = createEl('label', null, '有效期限（MM/YY）');
        l2.setAttribute('for', `buygo_${route}_card_expiry`);
        const i2 = document.createElement('input');
        i2.type = 'tel';
        i2.id = `buygo_${route}_card_expiry`;
        i2.autocomplete = 'cc-exp';
        i2.inputMode = 'numeric';
        i2.placeholder = '12/30';
        f2.appendChild(l2);
        f2.appendChild(i2);

        const f3 = createEl('div', 'field');
        const l3 = createEl('label', null, '安全碼（CVC）');
        l3.setAttribute('for', `buygo_${route}_card_cvc`);
        const i3 = document.createElement('input');
        i3.type = 'tel';
        i3.id = `buygo_${route}_card_cvc`;
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

        const hint2 = createEl(
          'div',
          'hint small muted',
          route === 'payuni_subscription'
            ? '提示：初次付款會導向 3D 驗證頁，完成後會回到收據頁。'
            : '提示：信用卡可站內付款；ATM/超商送出後會在收據頁顯示取號資訊。'
        );
        section.appendChild(hint2);

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

      if (allowPayTypeSelect && payType === 'atm') {
        const section = createEl('div', 'section');
        section.appendChild(createEl('div', 'section-title', 'ATM 設定'));

        const grid = createEl('div', 'grid');

        const f1 = createEl('div', 'field');
        const l1 = createEl('label', null, '轉帳銀行');
        l1.setAttribute('for', `buygo_${route}_bank_type`);
        const s1 = document.createElement('select');
        s1.id = `buygo_${route}_bank_type`;

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
        window[`is_${route}_ready`] = true;
      } catch (e) {
        // ignore
      }
    }

    function enableCheckoutButton(event) {
      const submitButton = window.fluentcart_checkout_vars?.submit_button;
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

    function run(event) {
      const container = findContainer(route);
      if (!container) {
        return;
      }

      render(container);
      markReady();
      enableCheckoutButton(event);
    }

    window.addEventListener(`fluent_cart_load_payments_${route}`, run);
    window.addEventListener(`fluent_cart_load_payments_${route.replace(/_/g, '-')}`, run);

    window.__buygoFcPayuniUiRunners = window.__buygoFcPayuniUiRunners || {};
    window.__buygoFcPayuniUiRunners[route] = function (event) {
      run(event);
    };

    return {
      route,
      run,
      setPayType,
    };
  }

  const payuniUi = bindGatewayUi('payuni', { allowPayTypeSelect: true });
  const subUi = bindGatewayUi('payuni_subscription', {
    allowPayTypeSelect: true,
    allowedPayTypes: ['credit'],
    displayPayTypes: ['credit'],
  });

  function getSelectedMethod() {
    const el = document.querySelector("input[name='_fct_pay_method']:checked");
    return el ? el.value : '';
  }

  function runIfSelected(event) {
    const m = getSelectedMethod();
    if (m === 'payuni' && payuniUi?.run) {
      payuniUi.run(event);
      return;
    }
    if (m === 'payuni_subscription' && subUi?.run) {
      subUi.run(event);
      return;
    }
  }

  if (!window.__buygoFcPayuniUiBound) {
    window.__buygoFcPayuniUiBound = true;

    window.addEventListener('fluent_cart_after_checkout_js_loaded', runIfSelected);
    window.addEventListener('fluentCartFragmentsReplaced', function () {
      setTimeout(function () {
        runIfSelected();
      }, 0);
    });

    document.addEventListener('change', function (e) {
      const t = e && e.target;
      if (!t) {
        return;
      }

      if (t.name === '_fct_pay_method' && t.checked) {
        runIfSelected();
        return;
      }

      if (t.name === 'payuni_payment_type') {
        if (payuniUi?.setPayType) {
          payuniUi.setPayType(t.value);
        }
        if (subUi?.setPayType) {
          subUi.setPayType(t.value);
        }
        runIfSelected();
        return;
      }
    });
  }

  setTimeout(function () {
    runIfSelected();
  }, 0);
})();

