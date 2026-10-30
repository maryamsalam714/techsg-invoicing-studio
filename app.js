document.addEventListener("DOMContentLoaded", () => {
    "use strict";

    /* =========================================
       GLOBAL HELPERS
    ========================================= */

    const $ = (selector, parent = document) => {
        return parent.querySelector(selector);
    };

    const $$ = (selector, parent = document) => {
        return [...parent.querySelectorAll(selector)];
    };

    const body = document.body;


    /* =========================================
       SIDEBAR / MOBILE MENU
    ========================================= */

    const sidebar = $(".sidebar");
    const menuButton = $(".menu");

    if (menuButton && sidebar) {
        menuButton.addEventListener("click", () => {
            sidebar.classList.toggle("open");
            body.classList.toggle("sidebar-open");
        });
    }

    // Close sidebar when clicking outside on mobile
    document.addEventListener("click", (event) => {
        if (!sidebar || !sidebar.classList.contains("open")) {
            return;
        }

        const clickedInsideSidebar = sidebar.contains(event.target);
        const clickedMenu = menuButton && menuButton.contains(event.target);

        if (!clickedInsideSidebar && !clickedMenu) {
            sidebar.classList.remove("open");
            body.classList.remove("sidebar-open");
        }
    });


    /* =========================================
       ACTIVE NAVIGATION
    ========================================= */

    const currentPage = body.dataset.page;

    $$(".nav a[data-page]").forEach((link) => {
        const linkPage = link.dataset.page;

        if (linkPage === currentPage) {
            link.classList.add("active");
        } else {
            link.classList.remove("active");
        }
    });


    /* =========================================
       DROPDOWN MENUS
    ========================================= */

    $$(".dropdown-toggle").forEach((toggle) => {
        toggle.addEventListener("click", (event) => {
            event.stopPropagation();

            const dropdown = toggle.closest(".dropdown");

            if (!dropdown) {
                return;
            }

            $$(".dropdown.open").forEach((item) => {
                if (item !== dropdown) {
                    item.classList.remove("open");
                }
            });

            dropdown.classList.toggle("open");
        });
    });

    document.addEventListener("click", () => {
        $$(".dropdown.open").forEach((dropdown) => {
            dropdown.classList.remove("open");
        });
    });


    /* =========================================
       MODALS
    ========================================= */

    const openModal = (modal) => {
        if (!modal) {
            return;
        }

        modal.classList.add("open");
        body.classList.add("modal-open");

        const firstInput = modal.querySelector(
            "input:not([type='hidden']), select, textarea"
        );

        if (firstInput) {
            setTimeout(() => firstInput.focus(), 100);
        }
    };

    const closeModal = (modal) => {
        if (!modal) {
            return;
        }

        modal.classList.remove("open");

        if (!$$(".modal.open").length) {
            body.classList.remove("modal-open");
        }
    };

    $$("[data-modal]").forEach((button) => {
        button.addEventListener("click", (event) => {
            event.preventDefault();

            const modalId = button.dataset.modal;
            const modal = document.getElementById(modalId);

            openModal(modal);
        });
    });

    $$(".modal").forEach((modal) => {
        const closeButtons = $$(".close, [data-close-modal]", modal);

        closeButtons.forEach((button) => {
            button.addEventListener("click", (event) => {
                event.preventDefault();
                closeModal(modal);
            });
        });

        modal.addEventListener("click", (event) => {
            if (event.target === modal) {
                closeModal(modal);
            }
        });
    });

    // ESC closes modal
    document.addEventListener("keydown", (event) => {
        if (event.key !== "Escape") {
            return;
        }

        $$(".modal.open").forEach((modal) => {
            closeModal(modal);
        });
    });


    /* =========================================
       TOAST NOTIFICATIONS
    ========================================= */

    let toastTimer;

    const showToast = (message, type = "success") => {
        let toast = $(".app-toast");

        if (!toast) {
            toast = document.createElement("div");
            toast.className = "app-toast";

            toast.innerHTML = `
                <div class="app-toast-icon">
                    <i class="fa-solid fa-check"></i>
                </div>
                <div class="app-toast-content">
                    <strong>Success</strong>
                    <span></span>
                </div>
                <button class="app-toast-close" type="button">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            `;

            document.body.appendChild(toast);

            $(".app-toast-close", toast).addEventListener("click", () => {
                hideToast();
            });
        }

        const icon = $(".app-toast-icon i", toast);
        const title = $(".app-toast-content strong", toast);
        const text = $(".app-toast-content span", toast);

        if (type === "error") {
            icon.className = "fa-solid fa-circle-exclamation";
            title.textContent = "Error";
        } else if (type === "warning") {
            icon.className = "fa-solid fa-triangle-exclamation";
            title.textContent = "Warning";
        } else {
            icon.className = "fa-solid fa-check";
            title.textContent = "Success";
        }

        text.textContent = message;

        toast.classList.add("show");

        clearTimeout(toastTimer);

        toastTimer = setTimeout(() => {
            hideToast();
        }, 3000);
    };

    const hideToast = () => {
        const toast = $(".app-toast");

        if (toast) {
            toast.classList.remove("show");
        }
    };

    $$("[data-toast]").forEach((button) => {
        button.addEventListener("click", (event) => {
            const message = button.dataset.toast;

            if (!message) {
                return;
            }

            event.preventDefault();
            showToast(message);
        });
    });


    /* =========================================
       SEARCH / TABLE FILTER
    ========================================= */

    $$("[data-filter]").forEach((input) => {
        const targetSelector = input.dataset.filter;
        const target = $(targetSelector);

        if (!target) {
            return;
        }

        const tableBody = $("tbody", target);

        if (!tableBody) {
            return;
        }

        const rows = $$("tr", tableBody);

        input.addEventListener("input", () => {
            const query = input.value.trim().toLowerCase();

            rows.forEach((row) => {
                const text = row.textContent.toLowerCase();

                row.style.display =
                    !query || text.includes(query)
                        ? ""
                        : "none";
            });
        });
    });


    /* =========================================
       TABLE ROW ACTIONS
    ========================================= */

    $$("[data-action]").forEach((button) => {
        button.addEventListener("click", (event) => {
            const action = button.dataset.action;

            if (!action) {
                return;
            }

            switch (action) {
                case "view":
                    showToast("Opening record...");
                    break;

                case "edit":
                    showToast("Edit mode opened.");
                    break;

                case "delete":
                    handleDelete(button);
                    break;

                case "duplicate":
                    showToast("Record duplicated.");
                    break;

                default:
                    showToast("Action completed.");
            }
        });
    });

    const handleDelete = (button) => {
        const row = button.closest("tr");

        if (!row) {
            return;
        }

        const confirmed = window.confirm(
            "Are you sure you want to delete this record?"
        );

        if (!confirmed) {
            return;
        }

        row.style.opacity = "0";
        row.style.transform = "translateX(20px)";
        row.style.transition = "all .2s ease";

        setTimeout(() => {
            row.remove();
            showToast("Record deleted successfully.");
        }, 220);
    };


    /* =========================================
       FORM SUBMISSION
    ========================================= */

    $$("form[data-demo-form]").forEach((form) => {
        form.addEventListener("submit", (event) => {
            event.preventDefault();

            if (!validateForm(form)) {
                showToast(
                    "Please complete the required fields.",
                    "error"
                );
                return;
            }

            showToast("Information saved successfully.");

            const modal = form.closest(".modal");

            if (modal) {
                setTimeout(() => {
                    closeModal(modal);
                }, 500);
            }
        });
    });


    /* =========================================
       FORM VALIDATION
    ========================================= */

    const validateForm = (form) => {
        let valid = true;

        const requiredFields = $$(
            "input[required], select[required], textarea[required]",
            form
        );

        requiredFields.forEach((field) => {
            const value = field.value.trim();

            field.classList.remove("field-error");

            if (!value) {
                valid = false;
                field.classList.add("field-error");
            }
        });

        return valid;
    };

    $$("input, select, textarea").forEach((field) => {
        field.addEventListener("input", () => {
            field.classList.remove("field-error");
        });

        field.addEventListener("change", () => {
            field.classList.remove("field-error");
        });
    });


    /* =========================================
       DELETE BUTTONS
    ========================================= */

    $$("[data-delete]").forEach((button) => {
        button.addEventListener("click", (event) => {
            event.preventDefault();

            const targetSelector = button.dataset.delete;
            const target = targetSelector
                ? $(targetSelector)
                : button.closest("tr, .card");

            if (!target) {
                return;
            }

            const confirmed = window.confirm(
                "Are you sure you want to delete this item?"
            );

            if (!confirmed) {
                return;
            }

            target.style.opacity = "0";
            target.style.transform = "scale(.98)";
            target.style.transition = "all .2s ease";

            setTimeout(() => {
                target.remove();
                showToast("Item deleted successfully.");
            }, 220);
        });
    });


    /* =========================================
       STATUS FILTER
    ========================================= */

    $$("[data-status-filter]").forEach((select) => {
        const targetSelector = select.dataset.statusFilter;
        const table = $(targetSelector);

        if (!table) {
            return;
        }

        const rows = $$("tbody tr", table);

        select.addEventListener("change", () => {
            const selectedStatus = select.value.toLowerCase();

            rows.forEach((row) => {
                if (
                    !selectedStatus ||
                    selectedStatus === "all"
                ) {
                    row.style.display = "";
                    return;
                }

                const status = $(".status", row);

                if (!status) {
                    row.style.display = "none";
                    return;
                }

                row.style.display =
                    status.textContent.trim().toLowerCase() === selectedStatus
                        ? ""
                        : "none";
            });
        });
    });


    /* =========================================
       SELECT ALL CHECKBOX
    ========================================= */

    $$("[data-select-all]").forEach((masterCheckbox) => {
        const targetSelector = masterCheckbox.dataset.selectAll;
        const container = $(targetSelector);

        if (!container) {
            return;
        }

        const checkboxes = $$(
            "input[type='checkbox'][data-row-check]",
            container
        );

        masterCheckbox.addEventListener("change", () => {
            checkboxes.forEach((checkbox) => {
                checkbox.checked = masterCheckbox.checked;
            });

            updateSelectedCount(container);
        });

        checkboxes.forEach((checkbox) => {
            checkbox.addEventListener("change", () => {
                const checkedCount = checkboxes.filter(
                    (item) => item.checked
                ).length;

                masterCheckbox.checked =
                    checkedCount === checkboxes.length;

                masterCheckbox.indeterminate =
                    checkedCount > 0 &&
                    checkedCount < checkboxes.length;

                updateSelectedCount(container);
            });
        });
    });

    const updateSelectedCount = (container) => {
        const selected = $$(
            "input[type='checkbox'][data-row-check]:checked",
            container
        ).length;

        const counter = $("[data-selected-count]", container);

        if (counter) {
            counter.textContent = selected;
        }
    };


    /* =========================================
       NUMBER INPUTS
    ========================================= */

    $$("[data-number-input]").forEach((input) => {
        input.addEventListener("input", () => {
            let value = input.value.replace(/[^0-9.]/g, "");

            const parts = value.split(".");

            if (parts.length > 2) {
                value = parts[0] + "." + parts.slice(1).join("");
            }

            input.value = value;
        });
    });


    /* =========================================
       CURRENCY FORMATTER
    ========================================= */

    const formatCurrency = (value, currency = "USD") => {
        const amount = Number(value) || 0;

        return new Intl.NumberFormat("en-US", {
            style: "currency",
            currency,
            minimumFractionDigits: 2
        }).format(amount);
    };

    window.TechSG = {
        showToast,
        openModal,
        closeModal,
        formatCurrency
    };


    /* =========================================
       INVOICE CALCULATOR
    ========================================= */

    const calculateInvoice = () => {
        const rows = $$(".invoice-line-item");

        if (!rows.length) {
            return;
        }

        let subtotal = 0;

        rows.forEach((row) => {
            const quantityInput = $("[data-quantity]", row);
            const rateInput = $("[data-rate]", row);
            const totalElement = $("[data-line-total]", row);

            const quantity = Number(
                quantityInput?.value || 0
            );

            const rate = Number(
                rateInput?.value || 0
            );

            const lineTotal = quantity * rate;

            subtotal += lineTotal;

            if (totalElement) {
                totalElement.textContent =
                    formatCurrency(lineTotal);
            }
        });

        const taxInput = $("[data-tax]");
        const discountInput = $("[data-discount]");

        const taxRate = Number(
            taxInput?.value || 0
        );

        const discount = Number(
            discountInput?.value || 0
        );

        const tax = subtotal * (taxRate / 100);
        const total = subtotal + tax - discount;

        const subtotalElement = $("[data-subtotal]");
        const taxElement = $("[data-tax-total]");
        const discountElement = $("[data-discount-total]");
        const totalElement = $("[data-grand-total]");

        if (subtotalElement) {
            subtotalElement.textContent =
                formatCurrency(subtotal);
        }

        if (taxElement) {
            taxElement.textContent =
                formatCurrency(tax);
        }

        if (discountElement) {
            discountElement.textContent =
                formatCurrency(discount);
        }

        if (totalElement) {
            totalElement.textContent =
                formatCurrency(Math.max(total, 0));
        }
    };

    $$("[data-invoice-calculator]").forEach((container) => {
        $$("input, select", container).forEach((input) => {
            input.addEventListener("input", calculateInvoice);
            input.addEventListener("change", calculateInvoice);
        });

        calculateInvoice();
    });


    /* =========================================
       ADD INVOICE LINE
    ========================================= */

    $$("[data-add-line]").forEach((button) => {
        button.addEventListener("click", () => {
            const targetSelector = button.dataset.addLine;
            const container = $(targetSelector);

            if (!container) {
                return;
            }

            const template = $("[data-line-template]");

            if (!template) {
                return;
            }

            const clone = template.content.cloneNode(true);

            container.appendChild(clone);

            calculateInvoice();

            showToast("New line item added.");
        });
    });


    /* =========================================
       REMOVE INVOICE LINE
    ========================================= */

    document.addEventListener("click", (event) => {
        const button = event.target.closest(
            "[data-remove-line]"
        );

        if (!button) {
            return;
        }

        const row = button.closest(
            ".invoice-line-item, tr"
        );

        if (!row) {
            return;
        }

        row.remove();

        calculateInvoice();

        showToast("Line item removed.");
    });


    /* =========================================
       COPY TO CLIPBOARD
    ========================================= */

    $$("[data-copy]").forEach((button) => {
        button.addEventListener("click", async () => {
            const value = button.dataset.copy;

            if (!value) {
                return;
            }

            try {
                await navigator.clipboard.writeText(value);

                showToast("Copied to clipboard.");
            } catch (error) {
                showToast(
                    "Unable to copy.",
                    "error"
                );
            }
        });
    });


    /* =========================================
       PRINT
    ========================================= */

    $$("[data-print]").forEach((button) => {
        button.addEventListener("click", () => {
            window.print();
        });
    });


    /* =========================================
       EXPORT PLACEHOLDER
    ========================================= */

    $$("[data-export]").forEach((button) => {
        button.addEventListener("click", () => {
        const exportType = button.dataset.export || "file";

        // Prevent multiple clicks while export is processing
        if (button.dataset.exporting === "true") {
            return;
        }

        button.dataset.exporting = "true";

        const originalContent = button.innerHTML;

        button.innerHTML = `
            <i class="fa-solid fa-spinner fa-spin"></i>
            Preparing...
        `;

        button.disabled = true;

        setTimeout(() => {
            button.innerHTML = originalContent;
            button.disabled = false;
            button.dataset.exporting = "false";

            if (exportType === "csv") {
                exportTableToCSV(button);
                return;
            }

            showToast("Your export is ready.");
        }, 900);
    });
});


/* =========================================
   TABLE CSV EXPORT
========================================= */

const exportTableToCSV = (button) => {
    const tableSelector =
        button.dataset.exportTable ||
        ".table";

    const table = $(tableSelector);

    if (!table) {
        showToast(
            "No table found to export.",
            "error"
        );
        return;
    }

    const rows = $$("tr", table);

    if (!rows.length) {
        showToast(
            "There is no data to export.",
            "warning"
        );
        return;
    }

    const csv = rows.map((row) => {
        const cells = $$("th, td", row);

        return cells.map((cell) => {
            let value = cell.innerText
                .replace(/\s+/g, " ")
                .trim();

            // Escape double quotes for CSV
            value = value.replace(/"/g, '""');

            return `"${value}"`;
        }).join(",");
    }).join("\n");

    const blob = new Blob(
        [csv],
        {
            type: "text/csv;charset=utf-8;"
        }
    );

    const url = URL.createObjectURL(blob);

    const link = document.createElement("a");

    link.href = url;

    const date = new Date()
        .toISOString()
        .slice(0, 10);

    link.download = `techsg-export-${date}.csv`;

    document.body.appendChild(link);

    link.click();

    document.body.removeChild(link);

    URL.revokeObjectURL(url);

    showToast(
        "CSV exported successfully."
    );
};


/* =========================================
   SEARCH WITH KEYBOARD SHORTCUT
========================================= */

document.addEventListener("keydown", (event) => {
    // Ctrl + K / Cmd + K
    if (
        (event.ctrlKey || event.metaKey) &&
        event.key.toLowerCase() === "k"
    ) {
        event.preventDefault();

        const searchInput =
            $(".search input");

        if (searchInput) {
            searchInput.focus();
            searchInput.select();
        }
    }
});


/* =========================================
   AUTO DISMISS ALERTS
========================================= */

$$("[data-auto-dismiss]").forEach((alert) => {
    const duration =
        Number(alert.dataset.autoDismiss) || 4000;

    setTimeout(() => {
        alert.style.opacity = "0";
        alert.style.transform =
            "translateY(-8px)";

        alert.style.transition =
            "all .25s ease";

        setTimeout(() => {
            alert.remove();
        }, 250);
    }, duration);
});


/* =========================================
   CONFIRM ACTION
========================================= */

$$("[data-confirm]").forEach((button) => {
    button.addEventListener("click", (event) => {
        const message =
            button.dataset.confirm ||
            "Are you sure you want to continue?";

        const confirmed =
            window.confirm(message);

        if (!confirmed) {
            event.preventDefault();
            return;
        }
    });
});


/* =========================================
   BUTTON LOADING STATE
========================================= */

$$("[data-loading]").forEach((button) => {
    button.addEventListener("click", () => {
        if (button.dataset.loadingState === "true") {
            return;
        }

        button.dataset.loadingState = "true";

        const originalContent =
            button.innerHTML;

        button.dataset.originalContent =
            originalContent;

        button.innerHTML = `
            <i class="fa-solid fa-spinner fa-spin"></i>
            Processing...
        `;

        button.disabled = true;

        const duration =
            Number(button.dataset.loadingDuration) ||
            1200;

        setTimeout(() => {
            button.innerHTML =
                button.dataset.originalContent;

            button.disabled = false;
            button.dataset.loadingState = "false";
        }, duration);
    });
});


/* =========================================
   DATE FORMATTER
========================================= */

const formatDate = (dateValue) => {
    if (!dateValue) {
        return "";
    }

    const date = new Date(dateValue);

    if (Number.isNaN(date.getTime())) {
        return dateValue;
    }

    return new Intl.DateTimeFormat(
        "en-US",
        {
            day: "2-digit",
            month: "short",
            year: "numeric"
        }
    ).format(date);
};

window.TechSG.formatDate = formatDate;


/* =========================================
   LIVE DATE FIELDS
========================================= */

$$("[data-format-date]").forEach((element) => {
    const value = element.dataset.formatDate;

    if (value) {
        element.textContent =
            formatDate(value);
    }
});


/* =========================================
   PASSWORD VISIBILITY
========================================= */

$$("[data-password-toggle]").forEach((button) => {
    button.addEventListener("click", () => {
        const inputSelector =
            button.dataset.passwordToggle;

        const input =
            $(inputSelector);

        if (!input) {
            return;
        }

        const icon =
            $("i", button);

        if (input.type === "password") {
            input.type = "text";

            if (icon) {
                icon.className =
                    "fa-regular fa-eye-slash";
            }

            button.setAttribute(
                "aria-label",
                "Hide password"
            );
        } else {
            input.type = "password";

            if (icon) {
                icon.className =
                    "fa-regular fa-eye";
            }

            button.setAttribute(
                "aria-label",
                "Show password"
            );
        }
    });
});


/* =========================================
   COPY INVOICE NUMBER
========================================= */

$$("[data-copy-invoice]").forEach((button) => {
    button.addEventListener("click", async () => {
        const invoiceNumber =
            button.dataset.copyInvoice;

        if (!invoiceNumber) {
            return;
        }

        try {
            await navigator.clipboard.writeText(
                invoiceNumber
            );

            showToast(
                `${invoiceNumber} copied.`
            );
        } catch (error) {
            showToast(
                "Unable to copy invoice number.",
                "error"
            );
        }
    });
});


/* =========================================
   SIDEBAR NAVIGATION
========================================= */

$$(".nav a").forEach((link) => {
    link.addEventListener("click", () => {
        if (
            window.innerWidth <= 1100 &&
            sidebar
        ) {
            sidebar.classList.remove("open");
            body.classList.remove(
                "sidebar-open"
            );
        }
    });
});


/* =========================================
   SMOOTH PAGE TRANSITION
========================================= */

$$("a[href]").forEach((link) => {
    const href = link.getAttribute("href");

    if (
        !href ||
        href.startsWith("#") ||
        href.startsWith("http") ||
        href.startsWith("mailto:") ||
        href.startsWith("tel:")
    ) {
        return;
    }

    link.addEventListener("click", (event) => {
        const target =
            link.getAttribute("target");

        if (target === "_blank") {
            return;
        }

        event.preventDefault();

        const destination = href;

        body.classList.add(
            "page-leaving"
        );

        setTimeout(() => {
            window.location.href =
                destination;
        }, 120);
    });
});


/* =========================================
   TABLE SORTING
========================================= */

$$("[data-sortable]").forEach((table) => {
    const headers = $$(
        "thead th",
        table
    );

    headers.forEach((header, columnIndex) => {
        header.style.cursor = "pointer";

        header.addEventListener("click", () => {
            sortTable(
                table,
                columnIndex,
                header
            );
        });
    });
});

const sortTable = (
    table,
    columnIndex,
    header
) => {
    const tbody = $("tbody", table);

    if (!tbody) {
        return;
    }

    const rows = $$("tr", tbody);

    const currentDirection =
        header.dataset.sortDirection ||
        "asc";

    const newDirection =
        currentDirection === "asc"
            ? "desc"
            : "asc";

    headersReset(table);

    header.dataset.sortDirection =
        newDirection;

    rows.sort((rowA, rowB) => {
        const cellA =
            $$("td", rowA)[columnIndex];

        const cellB =
            $$("td", rowB)[columnIndex];

        if (!cellA || !cellB) {
            return 0;
        }

        const valueA =
            cellA.innerText
                .trim()
                .toLowerCase();

        const valueB =
            cellB.innerText
                .trim()
                .toLowerCase();

        const numberA =
            parseFloat(
                valueA.replace(/[^0-9.-]/g, "")
            );

        const numberB =
            parseFloat(
                valueB.replace(/[^0-9.-]/g, "")
            );

        let comparison = 0;

        if (
            !Number.isNaN(numberA) &&
            !Number.isNaN(numberB)
        ) {
            comparison =
                numberA - numberB;
        } else {
            comparison =
                valueA.localeCompare(
                    valueB
                );
        }

        return newDirection === "asc"
            ? comparison
            : -comparison;
    });

    rows.forEach((row) => {
        tbody.appendChild(row);
    });
};

const headersReset = (table) => {
    $$(
        "thead th",
        table
    ).forEach((header) => {
        header.dataset.sortDirection = "";
    });
};


/* =========================================
   AUTO CALCULATE TOTALS
========================================= */

$$("[data-calculate-total]").forEach((container) => {
    const inputs = $$(
        "[data-calc-value]",
        container
    );

    const output = $(
        "[data-calc-total]",
        container
    );

    if (!inputs.length || !output) {
        return;
    }

    const calculate = () => {
        let total = 0;

        inputs.forEach((input) => {
            total +=
                Number(input.value) || 0;
        });

        output.textContent =
            formatCurrency(total);
    };

    inputs.forEach((input) => {
        input.addEventListener(
            "input",
            calculate
        );
    });

    calculate();
});


/* =========================================
   PRINT INVOICE
========================================= */

$$("[data-print-invoice]").forEach((button) => {
    button.addEventListener("click", () => {
        const invoice =
            $("[data-invoice-preview]");

        if (!invoice) {
            window.print();
            return;
        }

        document.body.classList.add(
            "printing-invoice"
        );

        window.print();

        setTimeout(() => {
            document.body.classList.remove(
                "printing-invoice"
            );
        }, 500);
    });
});


/* =========================================
   NOTIFICATION BUTTON
========================================= */

$$("[data-notifications]").forEach((button) => {
    button.addEventListener("click", () => {
        showToast(
            "You're all caught up."
        );
    });
});


/* =========================================
   HELP BUTTON
========================================= */

$$("[data-help]").forEach((button) => {
    button.addEventListener("click", () => {
        showToast(
            "Help center is coming soon."
        );
    });
});


/* =========================================
   INITIALIZE
========================================= */

document.documentElement.classList.add(
    "js-enabled"
);

setTimeout(() => {
    body.classList.add("loaded");
}, 50);

});