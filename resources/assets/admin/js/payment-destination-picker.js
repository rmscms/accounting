/**
 * کانال تسویه + مقصد + روش پرداخت — پر کردن hiddenها برای فرم ساختاریافته.
 */
(function ($) {
    'use strict';

    var CHANNEL_ICONS = {
        bank: 'ph-bank',
        cash_box: 'ph-coins',
        cheque: 'ph-check-square',
        pos: 'ph-credit-card',
        wallet: 'ph-wallet'
    };

    function readSetupRoutes($root) {
        var raw = $root.attr('data-setup-routes');
        if (!raw) {
            return {};
        }
        try {
            return JSON.parse(raw);
        } catch (e) {
            return {};
        }
    }

    function setHidden($root, name, val) {
        var $inp = $root.find('[data-pdp-field="' + name + '"]');
        $inp.val(val === null || val === undefined ? '' : String(val));
    }

    function clearDestinations($root) {
        setHidden($root, 'bank_id', '');
        setHidden($root, 'cash_box_id', '');
        setHidden($root, 'cheque_id', '');
        setHidden($root, 'pos_terminal_id', '');
        setHidden($root, 'wallet_id', '');
    }

    function applySelection($root, channelId, destId, methodId) {
        clearDestinations($root);
        setHidden($root, 'payment_method_id', methodId || '');
        if (!channelId || !destId) {
            return;
        }
        if (channelId === 'bank') {
            setHidden($root, 'bank_id', destId);
        } else if (channelId === 'cash_box') {
            setHidden($root, 'cash_box_id', destId);
        } else if (channelId === 'cheque') {
            setHidden($root, 'cheque_id', destId);
        } else if (channelId === 'pos') {
            setHidden($root, 'pos_terminal_id', destId);
        } else if (channelId === 'wallet') {
            setHidden($root, 'wallet_id', destId);
        }
    }

    /** فقط مقصد تسویه؛ payment_method_id خالی می‌ماند تا کاربر روش را صریح انتخاب کند (سرور در غیر این صورت خطا می‌دهد). */
    function applyDestinationOnly($root, channelId, destId) {
        clearDestinations($root);
        setHidden($root, 'payment_method_id', '');
        if (!channelId || !destId) {
            return;
        }
        if (channelId === 'bank') {
            setHidden($root, 'bank_id', destId);
        } else if (channelId === 'cash_box') {
            setHidden($root, 'cash_box_id', destId);
        } else if (channelId === 'cheque') {
            setHidden($root, 'cheque_id', destId);
        } else if (channelId === 'pos') {
            setHidden($root, 'pos_terminal_id', destId);
        } else if (channelId === 'wallet') {
            setHidden($root, 'wallet_id', destId);
        }
    }

    function renderEmptyState($root, channel, setup, tr) {
        var $box = $root.find('[data-pdp-empty]');
        var $msg = $root.find('[data-pdp-empty-msg]');
        var $links = $root.find('[data-pdp-empty-links]');
        $links.empty();
        var keyMap = {
            bank: 'banks',
            cash_box: 'cashboxes',
            cheque: 'cheques',
            pos: 'pos_terminals',
            wallet: 'wallets'
        };
        var sk = keyMap[channel.id] || channel.id;
        $msg.text(tr('empty_' + sk, tr('empty_generic', 'Nothing to select yet.')));
        if (!$msg.text()) {
            $msg.text(tr('empty_generic', 'Nothing to select yet.'));
        }
        var labels = {
            banks: tr('link_banks', 'Banks'),
            cashboxes: tr('link_cashboxes', 'Cash boxes'),
            cheques: tr('link_cheques', 'Cheques'),
            pos_terminals: tr('link_pos', 'POS terminals'),
            wallets: tr('link_wallets', 'Wallets'),
            payment_methods: tr('link_payment_methods', 'Payment methods')
        };
        var channelSetupUrl = setup[sk];
        if (channelSetupUrl) {
            var ctaText = tr('empty_create_hint', 'You can create :label from here.');
            ctaText = ctaText.replace(':label', labels[sk] || sk);
            $links.append($('<div class="w-100 small text-muted"></div>').text(ctaText));
            $links.append(
                $('<a class="btn btn-primary btn-sm rounded-pill px-3"></a>')
                    .attr('href', channelSetupUrl)
                    .text(tr('create_from_here', 'Create from here'))
            );
        }
        [['banks', setup.banks], ['cashboxes', setup.cashboxes], ['cheques', setup.cheques], ['pos_terminals', setup.pos_terminals], ['wallets', setup.wallets], ['payment_methods', setup.payment_methods]].forEach(function (pair) {
            var k = pair[0];
            var url = pair[1];
            if (url && !(k === sk && channelSetupUrl && String(url) === String(channelSetupUrl))) {
                var $a = $('<a class="btn btn-light btn-sm border rounded-pill px-3"></a>').attr('href', url).text(labels[k] || k);
                $links.append($a);
            }
        });
        $box.removeClass('d-none');
    }

    function mountPicker($root) {
        var L = {};
        var i18nRaw = $root.attr('data-i18n');
        if (i18nRaw) {
            try {
                L = JSON.parse(i18nRaw) || {};
            } catch (e2) {
                L = {};
            }
        }
        function t(key, fallback) {
            return (L && L[key]) ? L[key] : fallback;
        }
        function channelLabel(id) {
            return t('channel_' + id, id);
        }
        function channelIconClass(id) {
            return CHANNEL_ICONS[id] || 'ph-circle-wavy';
        }
        var radioNs = 'pdp_m_' + String(Math.random()).slice(2, 10);
        var url = $root.data('catalog-url');
        var setup = readSetupRoutes($root);
        var $err = $root.find('[data-pdp-error]');
        var $loading = $root.find('[data-pdp-loading]');
        var $main = $root.find('[data-pdp-main]');
        $err.addClass('d-none').text('');

        $.getJSON(url).done(function (data) {
            $loading.addClass('d-none');
            var channels = (data && data.channels) ? data.channels : [];
            if (!channels.length) {
                $err.removeClass('d-none').text(t('no_channels', 'No payment channels are available.'));
                return;
            }
            $main.removeClass('d-none');
            var $ch = $root.find('[data-pdp-channels]');
            $ch.empty();

            var state = {
                channels: channels,
                activeChannel: null,
                activeDestId: null,
                activeMethodId: null
            };

            function syncFromState() {
                if (!state.activeChannel || !state.activeDestId) {
                    setHidden($root, 'payment_method_id', '');
                    clearDestinations($root);
                    return;
                }
                var methods = state.activeChannel.payment_methods || [];
                var mid = state.activeMethodId;
                var methodValid = mid != null && mid !== '' && methods.some(function (m) {
                    return String(m.id) === String(mid);
                });
                if (methodValid) {
                    applySelection($root, state.activeChannel.id, state.activeDestId, mid);
                } else {
                    applyDestinationOnly($root, state.activeChannel.id, state.activeDestId);
                }
            }

            function renderDestinations() {
                var ch = state.activeChannel;
                var $wrap = $root.find('[data-pdp-dest-wrap]');
                var $list = $root.find('[data-pdp-destinations]');
                var $empty = $root.find('[data-pdp-empty]');
                $empty.addClass('d-none');
                $list.empty();
                if (!ch) {
                    $wrap.addClass('d-none');
                    return;
                }
                var dests = ch.destinations || [];
                if (!dests.length) {
                    $wrap.addClass('d-none');
                    renderEmptyState($root, ch, setup, t);
                    state.activeDestId = null;
                    syncFromState();
                    return;
                }
                $wrap.removeClass('d-none');
                dests.forEach(function (d) {
                    var active = String(d.id) === String(state.activeDestId);
                    var $col = $('<div class="col"></div>');
                    var $item = $('<button type="button" class="accounting-pdp-dest btn w-100 text-start border rounded-3 p-3 bg-body shadow-none"></button>');
                    if (active) {
                        $item.addClass('is-active');
                    }
                    var $title = $('<div class="fw-semibold text-body"></div>').text(d.label || ('#' + d.id));
                    $item.append($title);
                    if (d.subtitle) {
                        $item.append($('<div class="small text-muted mt-1"></div>').text(d.subtitle));
                    }
                    $item.on('click', function () {
                        state.activeDestId = d.id;
                        renderDestinations();
                        renderMethods();
                        syncFromState();
                    });
                    $col.append($item);
                    $list.append($col);
                });
            }

            function syncMethodLabels($m) {
                $m.find('label.accounting-pdp-method-label').removeClass('accounting-pdp-method--on border-primary');
                $m.find('input[type="radio"]:checked').closest('label.accounting-pdp-method-label').addClass('accounting-pdp-method--on border-primary');
            }

            function renderMethods() {
                var ch = state.activeChannel;
                var $mwrap = $root.find('[data-pdp-method-wrap]');
                var $m = $root.find('[data-pdp-methods]');
                $m.empty();
                if (!ch || !state.activeDestId) {
                    $mwrap.addClass('d-none');
                    return;
                }
                var methods = ch.payment_methods || [];
                if (!methods.length) {
                    $mwrap.addClass('d-none');
                    return;
                }
                $mwrap.removeClass('d-none');
                if (methods.length > 1 && state.activeMethodId != null && state.activeMethodId !== '') {
                    var stillOk = methods.some(function (m) {
                        return String(m.id) === String(state.activeMethodId);
                    });
                    if (!stillOk) {
                        state.activeMethodId = null;
                    }
                }
                methods.forEach(function (m) {
                    var id = 'pdp-m-' + radioNs + '-' + ch.id + '-' + m.id;
                    var $lab = $('<label class="accounting-pdp-method-label d-flex align-items-center gap-3 border rounded-3 px-3 py-2 mb-0 bg-body"></label>').attr('for', id);
                    var $radio = $('<input type="radio" class="form-check-input flex-shrink-0 mt-0">').attr('name', radioNs).attr('id', id).val(String(m.id));
                    if (String(state.activeMethodId) === String(m.id)) {
                        $radio.prop('checked', true);
                    }
                    $radio.on('change', function () {
                        if (this.checked) {
                            state.activeMethodId = m.id;
                            syncMethodLabels($m);
                            syncFromState();
                        }
                    });
                    $lab.on('click', function () {
                        window.setTimeout(function () {
                            syncMethodLabels($m);
                        }, 0);
                    });
                    $lab.append($radio);
                    $lab.append($('<span class="flex-grow-1"></span>').text(m.name || ('#' + m.id)));
                    $m.append($lab);
                });
                if (methods.length === 1) {
                    state.activeMethodId = methods[0].id;
                    $m.find('input[type="radio"]').prop('checked', true);
                }
                syncMethodLabels($m);
                syncFromState();
            }

            channels.forEach(function (ch, idx) {
                var $li = $('<li class="nav-item"></li>');
                var $btn = $('<button type="button" class="nav-link d-inline-flex align-items-center justify-content-center gap-2"></button>');
                var ic = channelIconClass(ch.id);
                $btn.append($('<i class="flex-shrink-0" aria-hidden="true"></i>').addClass(ic));
                $btn.append($('<span></span>').text(channelLabel(ch.id)));
                if (idx === 0) {
                    $btn.addClass('active');
                    state.activeChannel = ch;
                }
                $btn.on('click', function (e) {
                    e.preventDefault();
                    $ch.find('.nav-link').removeClass('active');
                    $btn.addClass('active');
                    state.activeChannel = ch;
                    state.activeDestId = null;
                    state.activeMethodId = null;
                    renderDestinations();
                    renderMethods();
                    syncFromState();
                });
                $li.append($btn);
                $ch.append($li);
            });

            function setNavActive(channelId) {
                $ch.find('.nav-link').removeClass('active');
                $ch.find('.nav-item').each(function (i) {
                    if (channels[i] && channels[i].id === channelId) {
                        $(this).find('.nav-link').addClass('active');
                    }
                });
            }

            function applyProgrammaticSelection(payload) {
                payload = payload && typeof payload === 'object' ? payload : {};
                var channelId = String(payload.channelId || '').trim();
                var destinationId = payload.destinationId;
                var paymentMethodId = payload.paymentMethodId;
                if (!channelId || destinationId === null || destinationId === undefined || String(destinationId).trim() === '') {
                    state.activeDestId = null;
                    state.activeMethodId = null;
                    renderDestinations();
                    renderMethods();
                    syncFromState();
                    return;
                }

                var target = channels.find(function (x) {
                    return x.id === channelId;
                });
                if (!target) {
                    return;
                }

                state.activeChannel = target;
                state.activeDestId = destinationId;
                state.activeMethodId = paymentMethodId !== undefined && paymentMethodId !== null && String(paymentMethodId).trim() !== ''
                    ? paymentMethodId
                    : null;
                setNavActive(channelId);
                renderDestinations();
                renderMethods();
                syncFromState();
            }

            $root.off('accounting:payment-picker:set').on('accounting:payment-picker:set', function (_event, payload) {
                applyProgrammaticSelection(payload);
            });

            function applyInitial() {
                var pm = $root.data('initial-payment-method-id');
                var b = $root.data('initial-bank-id');
                var c = $root.data('initial-cash-box-id');
                var cq = $root.data('initial-cheque-id');
                var pos = $root.data('initial-pos-terminal-id');
                var w = $root.data('initial-wallet-id');
                var chId = null;
                var dest = null;
                if (b) {
                    chId = 'bank';
                    dest = b;
                } else if (c) {
                    chId = 'cash_box';
                    dest = c;
                } else if (cq) {
                    chId = 'cheque';
                    dest = cq;
                } else if (pos) {
                    chId = 'pos';
                    dest = pos;
                } else if (w) {
                    chId = 'wallet';
                    dest = w;
                }
                if (chId) {
                    var target = channels.find(function (x) {
                        return x.id === chId;
                    });
                    if (target) {
                        state.activeChannel = target;
                        state.activeDestId = dest;
                        state.activeMethodId = pm || null;
                        setNavActive(chId);
                    }
                }
                renderDestinations();
                if (state.activeDestId) {
                    renderMethods();
                }
                syncFromState();
            }

            applyInitial();
        }).fail(function () {
            $loading.addClass('d-none');
            $err.removeClass('d-none').text(t('load_failed', 'Could not load payment options.'));
        });
    }

    function initAll() {
        $('[data-payment-picker]').each(function () {
            mountPicker($(this));
        });
    }

    $(document).ready(initAll);
}(window.jQuery));
