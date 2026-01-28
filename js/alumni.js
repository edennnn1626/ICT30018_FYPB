document.addEventListener("DOMContentLoaded", function() {

    const alumniContainer = document.getElementById("alumniContainer");

    // Pagination container
    const paginationContainer = document.createElement("div");
    paginationContainer.id = "pagination";
    paginationContainer.className = "d-flex justify-content-center align-items-center mt-3 flex-wrap";
    alumniContainer.parentElement.appendChild(paginationContainer);

    const filters = {
        name: document.getElementById("alumniFilter"),
        program: document.getElementById("programFilter"),
        graduation: document.getElementById("graduationFilter"),
        sort: document.getElementById("sortResponses")
    };

    const ITEMS_PER_PAGE = 5;
    let currentPage = 1;
    let filteredBoxes = [];

    // Get text safely
    function getText(box, selector) {
        const el = box.querySelector(selector);
        return el ? el.textContent.trim() : "";
    }

    // Parse date (YYYY-MM-DD or MM/DD/YYYY)
    function parseDate(dateStr) {
        if (!dateStr) return new Date("1900-01-01");
        if (dateStr.includes("/")) {
            const parts = dateStr.split("/");
            return new Date(`${parts[2]}-${parts[0]}-${parts[1]}`);
        }
        return new Date(dateStr);
    }

    // Expand/collapse functionality
    function setupExpandCollapse() {
        alumniContainer.querySelectorAll(".response-header").forEach(header => {
            const expandIcon = header.querySelector(".response-expand-icon");
            if (expandIcon) {
                header.addEventListener("click", (e) => {
                    // Don't trigger expand/collapse if clicking edit/delete buttons
                    if (e.target.closest('.edit-btn') || e.target.closest('.delete-btn')) {
                        return;
                    }
                    header.parentElement.classList.toggle("expanded");
                });
            }
        });
    }

    // Apply filtering and sorting
    function applyFiltersAndSort() {
        const nameVal = filters.name.value.toLowerCase();
        const programVal = filters.program.value.toLowerCase();
        const gradVal = filters.graduation.value.toLowerCase();
        const sortVal = filters.sort.value;

        let boxes = Array.from(alumniContainer.querySelectorAll(".response-box"));

        // Filter
        boxes.forEach(box => {
            const n = getText(box, ".alumni-name").toLowerCase();
            const p = getText(box, ".alumni-program").toLowerCase();
            const g = getText(box, ".alumni-graduation").toLowerCase();
            const show =
                (!nameVal || n.includes(nameVal)) &&
                (!programVal || p.includes(programVal)) &&
                (!gradVal || g.includes(gradVal));

            box.style.display = show ? "" : "none";
        });

        // Sort visible
        filteredBoxes = boxes.filter(b => b.style.display !== "none");
        filteredBoxes.sort((a, b) => {
            const nameA = getText(a, ".alumni-name").toLowerCase();
            const nameB = getText(b, ".alumni-name").toLowerCase();
            const progA = getText(a, ".alumni-program").toLowerCase();
            const progB = getText(b, ".alumni-program").toLowerCase();
            const dateA = parseDate(getText(a, ".alumni-graduation"));
            const dateB = parseDate(getText(b, ".alumni-graduation"));

            switch (sortVal) {
                case "oldest":
                    return dateA - dateB;
                case "newest":
                    return dateB - dateA;
                case "program":
                    return progA.localeCompare(progB);
                default:
                    return nameA.localeCompare(nameB);
            }
        });

        currentPage = 1;
        renderPage();
        renderPagination();
    }

    // Render current page
    function renderPage() {
        filteredBoxes.forEach((box, idx) => {
            box.style.display =
                idx >= (currentPage - 1) * ITEMS_PER_PAGE &&
                idx < currentPage * ITEMS_PER_PAGE
                    ? ""
                    : "none";
        });
    }

    // Render pagination buttons
    function renderPagination() {
        paginationContainer.innerHTML = "";
        const totalPages = Math.ceil(filteredBoxes.length / ITEMS_PER_PAGE);
        if (totalPages <= 1) return;

        const createBtn = (text, page) => {
            const btn = document.createElement("button");
            btn.textContent = text;
            btn.className = "btn btn-sm btn-outline-primary mx-1 my-1 pagination-btn";
            if (page === currentPage) btn.classList.add("active");
            btn.addEventListener("click", () => {
                currentPage = page;
                renderPage();
                renderPagination();
            });
            return btn;
        };

        // First & Prev (ARROWS ONLY)
        if (currentPage > 1) {
            paginationContainer.appendChild(createBtn("«", 1));                 // First
            paginationContainer.appendChild(createBtn("‹", currentPage - 1));   // Prev
        }

        // Page numbers with ellipsis
        const pagesToShow = [];
        for (let i = 1; i <= totalPages; i++) {
            if (
                i <= 2 ||
                i > totalPages - 2 ||
                Math.abs(i - currentPage) <= 1
            ) {
                pagesToShow.push(i);
            } else if (pagesToShow[pagesToShow.length - 1] !== "...") {
                pagesToShow.push("...");
            }
        }

        pagesToShow.forEach(p => {
            if (p === "...") {
                const span = document.createElement("span");
                span.textContent = "...";
                span.className = "mx-1";
                paginationContainer.appendChild(span);
            } else {
                paginationContainer.appendChild(createBtn(p, p));
            }
        });

        // Next & Last (ARROWS ONLY)
        if (currentPage < totalPages) {
            paginationContainer.appendChild(createBtn("›", currentPage + 1));   // Next
            paginationContainer.appendChild(createBtn("»", totalPages));        // Last
        }
    }

    function formatExtraInfo(text) {
        if (!text || text === "[]" || text.trim() === "") return "No extra info";

        try {
            const obj = JSON.parse(text);
            if (typeof obj === "object" && obj !== null) {
                return Object.entries(obj)
                    .map(([k, v]) => `${k}: ${v}`)
                    .join(", ");
            }
            return text;
        } catch (e) {
            return text;
        }
    }

    function exportToCSV() {
        const boxes = filteredBoxes;
        if (!boxes.length) {
            alert("No alumni to export.");
            return;
        }

        let csv =
            "Name,Student ID,Program,Graduation Date,Email,Personal Email,Mobile No,Extra Info\n";

        boxes.forEach(box => {
            const extraText = formatExtraInfo(getText(box, ".alumni-extra"));

            const row = [
                getText(box, ".alumni-name"),
                "'" + getText(box, ".alumni-student-id"),
                getText(box, ".alumni-program"),
                "'" + getText(box, ".alumni-graduation"),
                getText(box, ".alumni-email"),
                getText(box, ".alumni-personal"),
                "'" + getText(box, ".alumni-mobile"),
                extraText
            ]
                .map(v => `"${v.replace(/"/g, '""')}"`)
                .join(",");

            csv += row + "\n";
        });

        const blob = new Blob([csv], {
            type: "text/csv;charset=utf-8;"
        });
        const link = document.createElement("a");
        link.href = URL.createObjectURL(blob);
        link.download = "alumni_directory.csv";
        link.click();
    }

    // Event listeners
    filters.name.addEventListener("input", applyFiltersAndSort);
    filters.program.addEventListener("change", applyFiltersAndSort);
    filters.graduation.addEventListener("input", applyFiltersAndSort);
    filters.sort.addEventListener("change", applyFiltersAndSort);
    document.getElementById("exportCsvBtn").addEventListener("click", exportToCSV);

    setupExpandCollapse();
    applyFiltersAndSort();
});