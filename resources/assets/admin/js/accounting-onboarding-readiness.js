(function () {
    "use strict";

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? String(meta.getAttribute("content") || "") : "";
    }

    function notify(message, type) {
        if (typeof window.showToast === "function") {
            window.showToast(message, type || "info");
            return;
        }
        // eslint-disable-next-line no-alert
        window.alert(message);
    }

    function parseJsonResponse(response) {
        return response.json().catch(function () {
            return {};
        }).then(function (payload) {
            if (response.ok) {
                return payload;
            }
            var message = payload && (payload.message || payload.error)
                ? (payload.message || payload.error)
                : "Request failed.";
            var error = new Error(String(message));
            error.payload = payload || {};
            throw error;
        });
    }

    function initChartInstallButton(root) {
        var button = root.querySelector(".js-chart-install-run");
        if (!button) {
            return;
        }

        var url = String(root.getAttribute("data-chart-install-url") || "").trim();
        if (url === "") {
            return;
        }

        var runningText = String(root.getAttribute("data-chart-install-running") || "Running install...");
        var defaultText = String(root.getAttribute("data-chart-install-button-text") || button.textContent || "").trim();

        button.addEventListener("click", function () {
            if (button.disabled) {
                return;
            }

            button.disabled = true;
            button.innerHTML = '<i class="ph-spinner-gap me-1 spinner"></i>' + runningText;
            notify(runningText, "info");

            fetch(url, {
                method: "POST",
                headers: {
                    "X-Requested-With": "XMLHttpRequest",
                    "X-CSRF-TOKEN": csrfToken(),
                    "Accept": "application/json"
                }
            })
                .then(parseJsonResponse)
                .then(function (payload) {
                    notify(String(payload.message || "Done."), "success");
                    window.location.reload();
                })
                .catch(function (error) {
                    notify(String(error.message || "Install failed."), "error");
                    button.disabled = false;
                    button.innerHTML = '<i class="ph-play me-1"></i>' + defaultText;
                });
        });
    }

    function initOnboardingReadiness() {
        var root = document.querySelector("[data-onboarding-readiness]");
        if (!root) {
            return;
        }
        initChartInstallButton(root);
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initOnboardingReadiness, { once: true });
    } else {
        initOnboardingReadiness();
    }
})();
