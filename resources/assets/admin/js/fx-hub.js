(function () {
    "use strict";

    const initFxHub = () => {
        const root = document.querySelector(".fx-hub-page");
        if (!root) return;

        const qs = (sel) => document.querySelector(sel);
        const val = (field) => String(qs(`[data-field="${field}"]`)?.value || "").trim();
        const csrf = () => String(document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "");
        const decimals = Number(root.getAttribute("data-amount-decimals") || 4);
        const targetDecimals = Number(root.getAttribute("data-target-decimals") || 2);
        const tolerance = 0.01;
        const isFiniteNumber = (n) => Number.isFinite(n) && !Number.isNaN(n);
        let driverField = "source_amount_base";

        const parseAmount = (raw) => {
            if (raw === null || raw === undefined) return 0;
            const normalized = String(raw)
                .trim()
                .replace(/,/g, "")
                .replace(/\s+/g, "")
                .replace(/[\u0660-\u0669]/g, (d) => String(d.charCodeAt(0) - 1632))
                .replace(/[\u06F0-\u06F9]/g, (d) => String(d.charCodeAt(0) - 1776));
            const n = Number(normalized);
            return isFiniteNumber(n) ? n : 0;
        };
        const num = (field) => parseAmount(val(field));
        const round = (n, p = decimals) => {
            const factor = Math.pow(10, p);
            return Math.round((Number(n) || 0) * factor) / factor;
        };
        const formatAmount = (n, p = decimals) => {
            const safe = round(n, p);
            return safe.toLocaleString("en-US", { minimumFractionDigits: p, maximumFractionDigits: p });
        };
        const setAmountField = (field, amount, p = decimals) => {
            const el = qs(`[data-field="${field}"]`);
            if (!el) return;
            el.value = formatAmount(amount, p);
        };
        const setWarning = (message = "") => {
            const box = qs('[data-role="fx-mismatch"]');
            if (!box) return;
            if (!message) {
                box.classList.add("d-none");
                box.textContent = "";
                return;
            }
            box.classList.remove("d-none");
            box.textContent = message;
        };
        const notify = (message, type = "info") => {
            if (typeof window.showToast === "function") {
                window.showToast(message, type);
                return;
            }
            const box = qs('[data-role="fx-status"]');
            if (!box) {
                // Fallback for environments where global toast is unavailable.
                // eslint-disable-next-line no-alert
                window.alert(message);
                return;
            }
            box.classList.remove("d-none", "alert-success", "alert-danger", "alert-warning", "alert-info");
            const alertClass = type === "success"
                ? "alert-success"
                : (type === "error" ? "alert-danger" : (type === "warning" ? "alert-warning" : "alert-info"));
            box.classList.add(alertClass);
            box.textContent = message;
        };
        const resolveFeeValueDecimals = () => (val("fee_type") === "percent" ? 6 : decimals);

        const recalculateFee = (sourceAmountBase) => {
            const feeType = val("fee_type") || "fixed";
            const feeValue = num("fee_value");
            let feeAmount = 0;
            if (feeType === "percent") {
                feeAmount = sourceAmountBase > 0 && feeValue > 0 ? (sourceAmountBase * feeValue) / 100 : 0;
            } else {
                feeAmount = feeValue > 0 ? feeValue : 0;
            }
            setAmountField("fee_amount", Math.max(0, feeAmount));

            return round(Math.max(0, feeAmount));
        };

        const updateFeeLabel = () => {
        const feeType = val("fee_type") || "fixed";
        const label = qs('[data-role="fee-value-label"]');
        if (!label) return;
        label.textContent = feeType === "percent" ? "مقدار کارمزد (درصد)" : "مقدار کارمزد (مبلغ ثابت)";
        const feeValueInput = qs('[data-field="fee_value"]');
        if (feeValueInput) {
            feeValueInput.setAttribute("data-decimals", String(resolveFeeValueDecimals()));
        }
        };

        const detectWalletCurrencyMismatch = () => {
        const walletEl = qs('[data-field="target_wallet_id"]');
        if (!walletEl) return "";
        const selected = walletEl.options[walletEl.selectedIndex];
        const walletCurrency = String(selected?.getAttribute("data-currency") || "").toUpperCase();
        const targetCurrency = String(val("target_currency_code") || "").toUpperCase();
        if (walletCurrency && targetCurrency && walletCurrency !== targetCurrency) {
            return "ارز کیف پول انتخاب‌شده با ارز مقصد فرم یکسان نیست.";
        }
        return "";
        };

        const recalculateAll = () => {
        let sourceAmountBase = num("source_amount_base");
            const targetAmountRaw = round(num("target_amount"), targetDecimals);
        const fxRate = num("fx_rate");
        if (!isFiniteNumber(fxRate) || fxRate <= 0) {
            recalculateFee(sourceAmountBase);
            setWarning("");
            return { ok: false };
        }

        if (driverField === "target_amount") {
            const provisionalFee = recalculateFee(sourceAmountBase);
            sourceAmountBase = round(targetAmountRaw * fxRate + provisionalFee);
            setAmountField("source_amount_base", sourceAmountBase);
                setAmountField("target_amount", targetAmountRaw, targetDecimals);
        }

        const feeAmountBase = recalculateFee(sourceAmountBase);
        let targetAmount = targetAmountRaw;
        if (driverField !== "target_amount") {
                targetAmount = round(Math.max(0, sourceAmountBase - feeAmountBase) / fxRate, targetDecimals);
                setAmountField("target_amount", targetAmount, targetDecimals);
        }

        const expectedTargetAmountBase = round(targetAmount * fxRate);
        const expectedSourceAmount = round(expectedTargetAmountBase + feeAmountBase);
        const mismatch = round(sourceAmountBase - expectedSourceAmount);
        const walletMismatch = detectWalletCurrencyMismatch();

        if (walletMismatch) {
            setWarning(walletMismatch);
        } else if (Math.abs(mismatch) > tolerance) {
            setWarning(`عدم تطابق محاسباتی: اختلاف ${formatAmount(mismatch)} در مبلغ منبع وجود دارد.`);
        } else {
            setWarning("");
        }

        return {
            ok: !walletMismatch && Math.abs(mismatch) <= tolerance,
            sourceAmountBase,
            targetAmount,
            fxRate,
            feeAmountBase,
            mismatch,
        };
        };

        document.addEventListener("input", function (e) {
        const target = e.target.closest('[data-field]');
        if (!target) return;
        const field = target.getAttribute("data-field");
        if (!field) return;
        if (["source_amount_base", "target_amount", "fx_rate", "fee_type", "fee_value"].includes(field)) {
            driverField = field === "target_amount" ? "target_amount" : "source_amount_base";
            updateFeeLabel();
            recalculateAll();
        }
        });
        document.addEventListener("change", function (e) {
        const target = e.target.closest('[data-field]');
        if (!target) return;
        const field = target.getAttribute("data-field");
        if (!field) return;
        if (field === "source_bank_id" && val("source_bank_id")) {
            const cash = qs('[data-field="source_cash_box_id"]');
            if (cash) cash.value = "";
        }
        if (field === "source_cash_box_id" && val("source_cash_box_id")) {
            const bank = qs('[data-field="source_bank_id"]');
            if (bank) bank.value = "";
        }
        if (["source_amount_base", "target_amount", "fx_rate", "fee_type", "fee_value", "target_wallet_id", "target_currency_code"].includes(field)) {
            driverField = field === "target_amount" ? "target_amount" : driverField;
            updateFeeLabel();
            recalculateAll();
        }
        });

        document.addEventListener("click", function (e) {
        const btn = e.target.closest('[data-action="fx-conversion-save"]');
        if (!btn) return;
        e.preventDefault();
        const url = String(qs("[data-fx-store-url]")?.getAttribute("data-fx-store-url") || "").trim();
        if (!url) return;

        const calc = recalculateAll();

        const sourceBankId = Number(val("source_bank_id") || 0);
        const sourceCashBoxId = Number(val("source_cash_box_id") || 0);
        if (sourceBankId > 0 && sourceCashBoxId > 0) {
            notify("فقط یکی از بانک یا صندوق را به‌عنوان منبع انتخاب کنید.", "warning");
            return;
        }
        const sourceChannelType = sourceBankId > 0 ? "bank" : "cash_box";
        const sourceChannelId = sourceBankId > 0 ? sourceBankId : sourceCashBoxId;

        if (sourceChannelId <= 0) {
            notify("یک منبع معتبر (بانک یا صندوق) انتخاب کنید.", "warning");
            return;
        }
        if (!val("target_currency_code")) {
            notify("ارز مقصد را انتخاب کنید.", "warning");
            return;
        }
        if (Number(val("target_wallet_id") || 0) <= 0) {
            notify("کیف پول مقصد را انتخاب کنید.", "warning");
            return;
        }
        if (!calc?.ok) {
            notify("مقادیر تبدیل/کارمزد با هم سازگار نیستند. ابتدا هشدار فرم را رفع کنید.", "warning");
            return;
        }

        const payload = {
            source_channel_type: sourceChannelType,
            source_channel_id: sourceChannelId,
            target_wallet_id: Number(val("target_wallet_id") || 0),
            target_currency_code: String(val("target_currency_code") || "USD").toUpperCase(),
            target_amount: calc.targetAmount,
            source_amount_base: calc.sourceAmountBase,
            fx_rate_to_base: calc.fxRate,
            fee_amount_base: calc.feeAmountBase,
            fee_type: val("fee_type") || "fixed",
            fee_value: num("fee_value"),
            notes: val("notes"),
        };
        if (!isFiniteNumber(payload.target_amount) || payload.target_amount <= 0) {
            notify("مبلغ ارز مقصد باید بزرگ‌تر از صفر باشد.", "warning");
            return;
        }
        if (!isFiniteNumber(payload.source_amount_base) || payload.source_amount_base <= 0) {
            notify("مبلغ خروجی از منبع باید بزرگ‌تر از صفر باشد.", "warning");
            return;
        }
        if (!isFiniteNumber(payload.fx_rate_to_base) || payload.fx_rate_to_base <= 0) {
            notify("نرخ تبدیل معتبر نیست.", "warning");
            return;
        }
        notify("در حال ثبت تبدیل ارز...", "info");

        fetch(url, {
            method: "POST",
            headers: {
                "X-Requested-With": "XMLHttpRequest",
                "X-CSRF-TOKEN": csrf(),
                "Content-Type": "application/json",
                "Accept": "application/json",
            },
            body: JSON.stringify(payload),
        })
            .then(async (resp) => {
                const data = await resp.json().catch(() => ({}));
                if (!resp.ok || !data.success) {
                    const msg = data?.message || Object.values(data?.errors || {}).flat().join(" / ");
                    throw new Error(msg || "ثبت تبدیل با خطا مواجه شد.");
                }
                const docText = data?.data?.document_id ? ` / سند ${data.data.document_id}` : "";
                notify(`تبدیل ارز ثبت شد (شناسه ${data.data.id}${docText})`, "success");
            })
            .catch((err) => {
                notify(err.message || "ثبت تبدیل انجام نشد.", "error");
            });
        });

        updateFeeLabel();
        recalculateAll();
    };

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initFxHub, { once: true });
    } else {
        initFxHub();
    }
})();

