(function () {
    "use strict";

    function parseConfig() {
        var node = document.getElementById("fiscal-close-wizard-config");
        if (!node) {
            return null;
        }
        try {
            return JSON.parse(String(node.textContent || "{}"));
        } catch (_error) {
            return null;
        }
    }

    function showToast(message, type) {
        if (typeof window.showToast === "function") {
            window.showToast(String(message || ""), type || "info");
            return;
        }
        // eslint-disable-next-line no-alert
        window.alert(String(message || ""));
    }

    function requestJson(url, csrf, data) {
        return fetch(url, {
            method: "POST",
            headers: {
                "X-CSRF-TOKEN": csrf || "",
                "X-Requested-With": "XMLHttpRequest",
                "Accept": "application/json",
                "Content-Type": "application/json"
            },
            body: JSON.stringify(data || {})
        }).then(function (response) {
            return response.json().catch(function () {
                return {};
            }).then(function (payload) {
                if (response.ok && payload && payload.ok) {
                    return payload.data || {};
                }
                var msg = (payload && payload.message) ? payload.message : "Request failed.";
                throw new Error(String(msg));
            });
        });
    }

    function stepButton(step) {
        return document.querySelector('.js-fy-step[data-step="' + step + '"]');
    }

    function stepStatus(step) {
        return document.querySelector('[data-step-status="' + step + '"]');
    }

    function setStatus(step, message, cssClass) {
        var el = stepStatus(step);
        if (!el) {
            return;
        }
        el.className = "mt-2 small js-step-status " + (cssClass || "text-muted");
        el.textContent = String(message || "");
    }

    function setDisabled(step, disabled) {
        var btn = stepButton(step);
        if (!btn) {
            return;
        }
        btn.disabled = !!disabled;
    }

    function renderTempRows(rows) {
        var body = document.querySelector(".js-temp-table-body");
        var wrap = document.querySelector(".js-temp-table-wrap");
        if (!body || !wrap) {
            return;
        }
        body.innerHTML = "";
        var list = Array.isArray(rows) ? rows : [];
        for (var i = 0; i < list.length; i++) {
            var row = list[i] || {};
            var tr = document.createElement("tr");
            var tdCode = document.createElement("td");
            var tdName = document.createElement("td");
            var tdNet = document.createElement("td");
            tdCode.textContent = String(row.code || "");
            tdName.textContent = String(row.name || "");
            tdNet.className = "text-end";
            tdNet.textContent = Number(row.net || 0).toLocaleString();
            tr.appendChild(tdCode);
            tr.appendChild(tdName);
            tr.appendChild(tdNet);
            body.appendChild(tr);
        }
        wrap.classList.remove("d-none");
    }

    function setPreviewValues(data) {
        var wrap = document.querySelector(".js-preview-wrap");
        var net = document.querySelector(".js-preview-net");
        var tax = document.querySelector(".js-preview-tax");
        if (!wrap || !net || !tax) {
            return;
        }
        var preview = data && data.preview ? data.preview : {};
        net.textContent = Number(preview.estimated_income_summary_after_tax || 0).toLocaleString();
        tax.textContent = Number((data.tax && data.tax.income_tax_expense) || 0).toLocaleString();
        wrap.classList.remove("d-none");
    }

    function initWizard() {
        var root = document.getElementById("fiscal-close-wizard");
        var cfg = parseConfig();
        if (!root || !cfg || !cfg.routes) {
            return;
        }

        function runStep(step, payload) {
            var route = cfg.routes[step];
            if (!route) {
                return Promise.reject(new Error("Route not configured."));
            }
            setStatus(step, cfg.messages.running || "Running...", "text-info");
            setDisabled(step, true);
            return requestJson(route, cfg.csrf, payload || {}).then(function (data) {
                setStatus(step, cfg.messages.done || "Done", "text-success");
                return data;
            }).catch(function (error) {
                setStatus(step, String(error.message || cfg.messages.failed || "Failed"), "text-danger");
                setDisabled(step, false);
                throw error;
            });
        }

        setDisabled("preview", true);
        setDisabled("execute", true);
        setDisabled("postcheck", true);
        setDisabled("openNext", true);

        var btnPrecheck = stepButton("precheck");
        var btnPreview = stepButton("preview");
        var btnExecute = stepButton("execute");
        var btnPostcheck = stepButton("postcheck");
        var btnOpenNext = stepButton("openNext");

        if (btnPrecheck) {
            btnPrecheck.addEventListener("click", function () {
                runStep("precheck").then(function (data) {
                    var temporary = (data && data.temporary_accounts) ? data.temporary_accounts : {};
                    renderTempRows(temporary.rows || []);
                    if (data && data.can_proceed) {
                        setDisabled("preview", false);
                    }
                }).catch(function (error) {
                    showToast(error.message, "error");
                });
            });
        }

        if (btnPreview) {
            btnPreview.addEventListener("click", function () {
                runStep("preview").then(function (data) {
                    setPreviewValues(data);
                    setDisabled("execute", false);
                }).catch(function (error) {
                    showToast(error.message, "error");
                });
            });
        }

        if (btnExecute) {
            btnExecute.addEventListener("click", function () {
                runStep("execute", { close_mode: "full_entries" }).then(function () {
                    setDisabled("postcheck", false);
                }).catch(function (error) {
                    showToast(error.message, "error");
                });
            });
        }

        if (btnPostcheck) {
            btnPostcheck.addEventListener("click", function () {
                runStep("postcheck").then(function (data) {
                    if (data && data.temporary_accounts_zero) {
                        setStatus("postcheck", cfg.messages.done || "Done", "text-success");
                        setDisabled("openNext", false);
                    } else {
                        setStatus("postcheck", "Temporary accounts are not zero.", "text-danger");
                    }
                }).catch(function (error) {
                    showToast(error.message, "error");
                });
            });
        }

        if (btnOpenNext) {
            btnOpenNext.addEventListener("click", function () {
                var nextIdEl = document.getElementById("next_fiscal_year_id");
                var createEl = document.getElementById("create_next");
                var payload = {
                    next_fiscal_year_id: nextIdEl ? String(nextIdEl.value || "").trim() : "",
                    create_next: createEl ? !!createEl.checked : false
                };
                runStep("openNext", payload).then(function () {
                    showToast(cfg.messages.done || "Done", "success");
                }).catch(function (error) {
                    showToast(error.message, "error");
                });
            });
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initWizard, { once: true });
    } else {
        initWizard();
    }
})();
