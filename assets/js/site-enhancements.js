(function () {
    const scriptEl =
        document.currentScript ||
        Array.from(document.querySelectorAll("script[src]")).find((script) =>
            script.src.includes("assets/js/site-enhancements.js")
        );

    const formEndpoint = scriptEl ? new URL("../../form-handler.php", scriptEl.src).href : "form-handler.php";

    const isFileProtocol = window.location.protocol === "file:";

    function escapeHtml(value) {
        return String(value)
            .replaceAll("&", "&amp;")
            .replaceAll("<", "&lt;")
            .replaceAll(">", "&gt;")
            .replaceAll('"', "&quot;")
            .replaceAll("'", "&#39;");
    }

    function getFeedbackBox(form) {
        let box =
            form.parentElement?.querySelector(".ff-errors-in-stack") ||
            form.parentElement?.querySelector(".seekoe-form-feedback") ||
            form.querySelector(".seekoe-form-feedback");

        if (!box) {
            box = document.createElement("div");
            box.className = "seekoe-form-feedback";
            box.setAttribute("aria-live", "polite");
            form.insertAdjacentElement("afterend", box);
        }

        return box;
    }

    function clearFeedback(form) {
        const box = getFeedbackBox(form);
        box.classList.remove("ff-errors-in-stack", "ff-message-success");
        box.innerHTML = "";
    }

    function showError(form, messages) {
        const box = getFeedbackBox(form);
        const items = Array.isArray(messages) ? messages : [messages];
        box.className = "ff-errors-in-stack seekoe-form-feedback";
        box.innerHTML = "<ul>" + items.map((message) => `<li>${escapeHtml(message)}</li>`).join("") + "</ul>";
    }

    function showSuccess(form, message) {
        const box = getFeedbackBox(form);
        box.className = "ff-message-success seekoe-form-feedback";
        box.textContent = message;
    }

    function toggleSubmitting(form, isSubmitting) {
        const submitButton = form.querySelector('button[type="submit"], input[type="submit"]');
        if (!submitButton) {
            return;
        }

        const textTarget =
            submitButton.tagName === "BUTTON"
                ? submitButton.querySelector(".elementor-button-text") || submitButton
                : submitButton;

        if (!submitButton.dataset.originalLabel) {
            submitButton.dataset.originalLabel =
                submitButton.tagName === "INPUT" ? submitButton.value : textTarget.textContent;
        }

        submitButton.disabled = isSubmitting;

        if (submitButton.tagName === "INPUT") {
            submitButton.value = isSubmitting ? "Sending..." : submitButton.dataset.originalLabel;
        } else {
            textTarget.textContent = isSubmitting ? "Sending..." : submitButton.dataset.originalLabel;
        }
    }

    function resetFormState(form) {
        form.querySelectorAll(".ff-message-success, .ff-errors-in-stack").forEach((el) => {
            if (el !== getFeedbackBox(form)) {
                el.remove();
            }
        });
    }

    function addFormMetadata(formData, form) {
        formData.append("_seekoe_page_url", window.location.href);
        formData.append("_seekoe_page_title", document.title);
        formData.append("_seekoe_form_id", form.id || "");
        formData.append("_seekoe_form_name", form.dataset.seekoeForm || form.getAttribute("data-form_id") || "");
    }

    async function submitForm(form, event) {
        event.preventDefault();
        event.stopImmediatePropagation();

        clearFeedback(form);
        resetFormState(form);

        if (isFileProtocol) {
            showError(form, "Form submission requires a PHP-enabled server instead of opening the HTML file directly.");
            return;
        }

        if (!form.reportValidity()) {
            return;
        }

        const formData = new FormData(form);
        addFormMetadata(formData, form);

        toggleSubmitting(form, true);

        try {
            const response = await fetch(formEndpoint, {
                method: "POST",
                body: formData,
                headers: {
                    Accept: "application/json",
                },
            });

            let payload = null;
            try {
                payload = await response.json();
            } catch (error) {
                payload = null;
            }

            if (!response.ok || !payload || !payload.success) {
                const message = payload?.message || "We could not send your form right now. Please try again in a moment.";
                const errors = payload?.errors?.length ? payload.errors : [message];
                showError(form, errors);
                return;
            }

            form.reset();
            showSuccess(form, payload.message || "Your submission has been sent successfully.");
        } catch (error) {
            showError(form, "Something went wrong while sending your submission. Please try again.");
        } finally {
            toggleSubmitting(form, false);
        }
    }

    function initForms() {
        const forms = document.querySelectorAll("form.frm-fluent-form, form[data-seekoe-form]");

        forms.forEach((form) => {
            if (form.dataset.seekoeBound === "true") {
                return;
            }

            form.dataset.seekoeBound = "true";
            form.setAttribute("novalidate", "novalidate");
            form.addEventListener(
                "submit",
                (event) => {
                    submitForm(form, event);
                },
                true
            );
        });
    }

    function initCareersFilters() {
        const filterRoot = document.querySelector("search.e-filter");
        const cards = Array.from(document.querySelectorAll(".elementor-loop-container .e-loop-item"));

        if (!filterRoot || !cards.length) {
            return;
        }

        const buttons = Array.from(filterRoot.querySelectorAll(".e-filter-item"));
        const pagination = document.querySelector(".elementor-pagination");
        const loadMoreAnchor = document.querySelector(".e-load-more-anchor");
        let emptyState = document.querySelector(".seekoe-careers-empty-state");

        if (!emptyState) {
            emptyState = document.createElement("p");
            emptyState.className = "seekoe-careers-empty-state";
            emptyState.textContent = "No roles match this category on the current page yet.";
            emptyState.style.display = "none";
            emptyState.style.marginTop = "16px";
            emptyState.style.fontFamily = "Poppins, Sans-serif";
            emptyState.style.color = "#1f365c";
            filterRoot.parentElement?.appendChild(emptyState);
        }

        function applyFilter(filterValue) {
            let visibleCount = 0;

            buttons.forEach((button) => {
                button.setAttribute("aria-pressed", String(button.dataset.filter === filterValue));
            });

            cards.forEach((card) => {
                const matches = filterValue === "__all" || card.classList.contains(`job-category-${filterValue}`);

                card.style.display = matches ? "" : "none";
                card.hidden = !matches;
                if (matches) {
                    visibleCount += 1;
                }
            });

            if (pagination) {
                pagination.style.display = filterValue === "__all" ? "" : "none";
            }

            if (loadMoreAnchor) {
                loadMoreAnchor.style.display = filterValue === "__all" ? "" : "none";
            }

            emptyState.style.display = visibleCount ? "none" : "block";
        }

        buttons.forEach((button) => {
            button.addEventListener("click", () => {
                applyFilter(button.dataset.filter || "__all");
            });
        });

        const initiallyActive =
            buttons.find((button) => button.getAttribute("aria-pressed") === "true")?.dataset.filter || "__all";

        applyFilter(initiallyActive);
    }

    document.addEventListener("DOMContentLoaded", () => {
        initForms();
        initCareersFilters();
    });
})();
