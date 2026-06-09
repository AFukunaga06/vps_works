(() => {
    const table = document.getElementById("readingTable");
    if (!table) {
        return;
    }

    const booksDataNode = document.getElementById("booksData");
    const verseCountsDataNode = document.getElementById("verseCountsData");
    const books = booksDataNode ? JSON.parse(booksDataNode.textContent || "[]") : [];
    const verseCounts = verseCountsDataNode ? JSON.parse(verseCountsDataNode.textContent || "{}") : {};
    const appBasePath = window.BIBLE_READING_APP?.basePath || "/bible_reading";

    const rows = Array.from(table.querySelectorAll(".reading-row"));
    const searchInput = document.getElementById("bookSearch");
    const testamentFilter = document.getElementById("testamentFilter");
    const statusFilter = document.getElementById("statusFilter");
    const resetFiltersButton = document.getElementById("resetFilters");
    const visibleCount = document.getElementById("visibleCount");

    const verseQuickForm = document.getElementById("verseQuickForm");
    const verseBookSelect = document.getElementById("verseBookSelect");
    const verseChapterSelect = document.getElementById("verseChapterSelect");
    const verseStartSelect = document.getElementById("verseStartSelect");
    const verseEndSelect = document.getElementById("verseEndSelect");
    const verseReadCheckbox = document.getElementById("verseReadCheckbox");
    const verseReadDate = document.getElementById("verseReadDate");
    const verseMemoInput = document.getElementById("verseMemoInput");
    const verseSaveStatus = document.getElementById("verseSaveStatus");
    const verseRecentList = document.getElementById("verseRecentList");

    const summaryNodes = {
        all: {
            value: document.querySelector('[data-summary-value="all"]'),
            percent: document.querySelector('[data-summary-percent="all"]'),
        },
        old: {
            value: document.querySelector('[data-summary-value="old"]'),
            percent: document.querySelector('[data-summary-percent="old"]'),
        },
        new: {
            value: document.querySelector('[data-summary-value="new"]'),
            percent: document.querySelector('[data-summary-percent="new"]'),
        },
    };

    function setStatus(node, message, isError = false) {
        if (!node) {
            return;
        }
        node.textContent = message;
        node.classList.toggle("is-error", isError);
    }

    function updateSummary(summary) {
        ["all", "old", "new"].forEach((key) => {
            if (!summary[key]) {
                return;
            }
            summaryNodes[key].value.textContent = `${summary[key].read}章 / ${summary[key].total}章`;
            summaryNodes[key].percent.textContent = `${Number(summary[key].percent).toFixed(1)}%`;
        });
    }

    async function postProgress(payload) {
        const response = await fetch(`${appBasePath}/save_progress.php`, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify(payload),
        });

        const result = await response.json();
        if (!response.ok || !result.success) {
            throw new Error(result.message || "保存に失敗しました。");
        }

        return result;
    }

    function collectChapterRowData(row) {
        return {
            progress_scope: "chapter",
            testament: row.dataset.testament,
            book_name: row.dataset.bookName,
            chapter: Number(row.dataset.chapter),
            verse_start: 0,
            verse_end: 0,
            is_read: row.querySelector(".read-checkbox").checked ? 1 : 0,
            read_date: row.querySelector(".read-date").value,
            memo: row.querySelector(".memo-input").value.trim(),
        };
    }

    function updateChapterRowState(row, data) {
        const isRead = Number(data.is_read) === 1;
        row.dataset.isRead = isRead ? "1" : "0";
        row.classList.toggle("is-read", isRead);

        const dateInput = row.querySelector(".read-date");
        if (data.read_date) {
            dateInput.value = data.read_date;
        } else if (!isRead) {
            dateInput.value = "";
        }
    }

    async function saveChapterRow(row) {
        const payload = collectChapterRowData(row);
        if (payload.is_read === 0) {
            payload.read_date = "";
        }

        setStatus(row.querySelector(".save-status"), "保存中...");

        try {
            const result = await postProgress(payload);
            updateChapterRowState(row, result.data);
            updateSummary(result.summary);
            setStatus(row.querySelector(".save-status"), "保存済み");
            applyFilters();
        } catch (error) {
            setStatus(row.querySelector(".save-status"), error.message || "保存エラー", true);
        }
    }

    function closeAllQuoteDetails() {
        rows.forEach((row) => {
            const detailRow = row.nextElementSibling;
            const button = row.querySelector(".quote-button");
            if (detailRow && detailRow.classList.contains("quote-detail-row")) {
                detailRow.hidden = true;
            }
            if (button) {
                button.setAttribute("aria-expanded", "false");
                button.textContent = "引用を見る";
            }
        });
    }

    function toggleQuoteDetail(row) {
        const detailRow = row.nextElementSibling;
        const button = row.querySelector(".quote-button");
        if (!detailRow || !detailRow.classList.contains("quote-detail-row") || !button) {
            return;
        }

        const willOpen = detailRow.hidden;
        closeAllQuoteDetails();
        detailRow.hidden = !willOpen;
        button.setAttribute("aria-expanded", willOpen ? "true" : "false");
        button.textContent = willOpen ? "引用を閉じる" : "引用を見る";
    }

    function applyFilters() {
        const keyword = searchInput.value.trim();
        const testament = testamentFilter.value;
        const status = statusFilter.value;
        let visible = 0;

        rows.forEach((row) => {
            const detailRow = row.nextElementSibling;
            const bookName = row.dataset.bookName || "";
            const isRead = row.dataset.isRead === "1";

            const keywordOk = keyword === "" || bookName.includes(keyword);
            const testamentOk = testament === "all" || row.dataset.testament === testament;
            const statusOk =
                status === "all" ||
                (status === "read" && isRead) ||
                (status === "unread" && !isRead);

            const shouldShow = keywordOk && testamentOk && statusOk;
            row.hidden = !shouldShow;

            if (!shouldShow && detailRow && detailRow.classList.contains("quote-detail-row")) {
                detailRow.hidden = true;
                const button = row.querySelector(".quote-button");
                if (button) {
                    button.setAttribute("aria-expanded", "false");
                    button.textContent = "引用を見る";
                }
            }

            if (shouldShow) {
                visible += 1;
            }
        });

        visibleCount.textContent = `表示件数: ${visible}件`;
    }

    function findSelectedBook() {
        return books.find((book) => book.book_name === verseBookSelect.value) || books[0] || null;
    }

    function fillSelectRange(select, start, end, selectedValue = null) {
        const currentValue = selectedValue ?? select.value;
        const options = [];

        for (let value = start; value <= end; value += 1) {
            const selected = String(value) === String(currentValue) ? " selected" : "";
            options.push(`<option value="${value}"${selected}>${value}</option>`);
        }

        select.innerHTML = options.join("");
        if (!select.value && start <= end) {
            select.value = String(start);
        }
    }

    function syncVerseChapterOptions() {
        const selectedBook = findSelectedBook();
        if (!selectedBook) {
            verseChapterSelect.innerHTML = "";
            return;
        }

        fillSelectRange(verseChapterSelect, 1, Number(selectedBook.chapters_count), verseChapterSelect.value || 1);
    }

    function syncVerseRangeOptions() {
        const selectedBook = findSelectedBook();
        const selectedChapter = Number(verseChapterSelect.value || 1);
        const maxVerse = Number(verseCounts?.[selectedBook?.book_name]?.[selectedChapter] || 1);

        fillSelectRange(verseStartSelect, 1, maxVerse, verseStartSelect.value || 1);
        fillSelectRange(verseEndSelect, 1, maxVerse, verseEndSelect.value || verseStartSelect.value || 1);

        if (Number(verseEndSelect.value) < Number(verseStartSelect.value)) {
            verseEndSelect.value = verseStartSelect.value;
        }
    }

    function renderRecentVerseItem(data) {
        return `
            <article class="recent-item" data-recent-key="${data.book_name}:${data.chapter}:${data.verse_start}:${data.verse_end}">
                <strong>${data.book_name} ${data.chapter}章 ${data.verse_start}-${data.verse_end}節</strong>
                <span>${data.read_date || "日付未設定"}</span>
                <p>${data.memo || "メモなし"}</p>
            </article>
        `;
    }

    function updateRecentVerseList(data) {
        const key = `${data.book_name}:${data.chapter}:${data.verse_start}:${data.verse_end}`;
        const existing = verseRecentList.querySelector(`[data-recent-key="${CSS.escape(key)}"]`);
        if (existing) {
            existing.remove();
        }

        verseRecentList.querySelector(".empty-state")?.remove();

        if (Number(data.is_read) === 0 && !data.memo) {
            if (!verseRecentList.children.length) {
                verseRecentList.innerHTML = '<p class="empty-state">まだ節単位の記録はありません。</p>';
            }
            return;
        }

        verseRecentList.insertAdjacentHTML("afterbegin", renderRecentVerseItem(data));

        const items = Array.from(verseRecentList.querySelectorAll(".recent-item"));
        items.slice(12).forEach((item) => item.remove());
    }

    async function saveVerseQuickForm() {
        const selectedBook = findSelectedBook();
        if (!selectedBook) {
            setStatus(verseSaveStatus, "書名が見つかりません。", true);
            return;
        }

        const payload = {
            progress_scope: "verse",
            testament: selectedBook.testament,
            book_name: selectedBook.book_name,
            chapter: Number(verseChapterSelect.value),
            verse_start: Number(verseStartSelect.value),
            verse_end: Number(verseEndSelect.value),
            is_read: verseReadCheckbox.checked ? 1 : 0,
            read_date: verseReadCheckbox.checked ? verseReadDate.value : "",
            memo: verseMemoInput.value.trim(),
        };

        setStatus(verseSaveStatus, "保存中...");

        try {
            const result = await postProgress(payload);
            updateSummary(result.summary);
            updateRecentVerseList(result.data);
            setStatus(verseSaveStatus, "保存済み");
        } catch (error) {
            setStatus(verseSaveStatus, error.message || "保存エラー", true);
        }
    }

    rows.forEach((row) => {
        const checkbox = row.querySelector(".read-checkbox");
        const dateInput = row.querySelector(".read-date");
        const memoInput = row.querySelector(".memo-input");
        const saveButton = row.querySelector(".save-button");
        const quoteButton = row.querySelector(".quote-button");

        checkbox.addEventListener("change", () => {
            if (checkbox.checked && !dateInput.value) {
                dateInput.value = new Date().toLocaleDateString("sv-SE");
            }
            if (!checkbox.checked) {
                dateInput.value = "";
            }
            saveChapterRow(row);
        });

        dateInput.addEventListener("change", () => {
            if (dateInput.value !== "") {
                checkbox.checked = true;
            }
            saveChapterRow(row);
        });

        memoInput.addEventListener("blur", () => saveChapterRow(row));
        saveButton.addEventListener("click", () => saveChapterRow(row));
        quoteButton.addEventListener("click", () => toggleQuoteDetail(row));
    });

    [searchInput, testamentFilter, statusFilter].forEach((node) => {
        node.addEventListener("input", applyFilters);
        node.addEventListener("change", applyFilters);
    });

    resetFiltersButton.addEventListener("click", () => {
        searchInput.value = "";
        testamentFilter.value = "all";
        statusFilter.value = "all";
        applyFilters();
    });

    verseBookSelect.addEventListener("change", () => {
        syncVerseChapterOptions();
        syncVerseRangeOptions();
    });
    verseChapterSelect.addEventListener("change", syncVerseRangeOptions);
    verseStartSelect.addEventListener("change", () => {
        if (Number(verseEndSelect.value) < Number(verseStartSelect.value)) {
            verseEndSelect.value = verseStartSelect.value;
        }
    });
    verseReadCheckbox.addEventListener("change", () => {
        if (verseReadCheckbox.checked && !verseReadDate.value) {
            verseReadDate.value = new Date().toLocaleDateString("sv-SE");
        }
        if (!verseReadCheckbox.checked) {
            verseReadDate.value = "";
        }
    });
    verseQuickForm.addEventListener("submit", (event) => {
        event.preventDefault();
        saveVerseQuickForm();
    });

    syncVerseChapterOptions();
    syncVerseRangeOptions();
    if (!verseReadDate.value) {
        verseReadDate.value = new Date().toLocaleDateString("sv-SE");
    }
    applyFilters();

    // ===== 訳切替 =====
    const btnBungo = document.getElementById("btnBungo");
    const btnKogo  = document.getElementById("btnKogo");
    let currentTranslation = localStorage.getItem("bible_translation") || "文語訳";

    function applyTranslation(translation) {
        currentTranslation = translation;
        localStorage.setItem("bible_translation", translation);
        document.querySelectorAll(".trans-content").forEach((el) => {
            el.hidden = el.dataset.translation !== translation;
        });
        if (btnBungo) btnBungo.classList.toggle("active", translation === "文語訳");
        if (btnKogo)  btnKogo.classList.toggle("active",  translation === "口語訳");
    }

    if (btnBungo) btnBungo.addEventListener("click", () => applyTranslation("文語訳"));
    if (btnKogo)  btnKogo.addEventListener("click",  () => applyTranslation("口語訳"));

    applyTranslation(currentTranslation);
})();
