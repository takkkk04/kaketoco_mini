// =============================================
// ハンバーガーメニュー
// =============================================
$(function () {
    const $btn = $("#menu_btn");
    const $panel = $("#menu_panel");

    function closeMenu() {
        $panel.prop("hidden", true);
        $btn.attr("aria-expanded", "false");
    }

    function toggleMenu() {
        const isOpen = !$panel.prop("hidden");
        if (isOpen) closeMenu();
        else {
            $panel.prop("hidden", false);
            $btn.attr("aria-expanded", "true");
        }
    }

    $btn.on("click", function (e) {
        e.stopPropagation();
        toggleMenu();
    });

    $panel.on("click", function (e) {
        e.stopPropagation();
    });

    $(document).on("click", function () {
        closeMenu();
    });

    $(document).on("keydown", function (e) {
        if (e.key === "Escape") closeMenu();
    });
});

// =============================================
// ザックリ検索プルダウン 入力で絞る処理
// =============================================
//Select2有効化、表記揺れ吸収
function normalizeJa(str) {
    if (!str) return "";
    //ひらがな→カタカナ
    str = str.replace(/[\u3041-\u3096]/g, function (match) {
        return String.fromCharCode(match.charCodeAt(0) + 0x60);
    });
    //半角→全角（カナ含む）
    if (typeof str.normalize === "function") {
        str = str.normalize("NFKC");
    }
    return str.toLowerCase();
}

$(function () {
    $(".js-select2").each(function () {
        const $el = $(this);
        const isSingleChip = $el.hasClass("js-select2-single-chip");
        const isMultiple = $el.prop("multiple");
        const placeholder = $el.data("placeholder") || "指定なし";

        $el.select2({
            width: "100%",
            placeholder: placeholder,
            allowClear: true,
            maximumSelectionLength: isMultiple && isSingleChip ? 1 : 0,
            closeOnSelect: isSingleChip,
            matcher: function (params, data) {
                if ($.trim(params.term) === "") {
                    return data;
                }
                if (!data.text) {
                    return null;
                }
                const term = normalizeJa(params.term);
                const text = normalizeJa(data.text);
                if (text.indexOf(term) !== -1) {
                    return data;
                }
                return null;
            }
        });
    });

    // プルダウンを開いた直後、検索欄をクリアしてフォーカス（害虫/病害/雑草含む全Select2）
    $(document).on("select2:open", function () {
        const focusOpenSearchField = function () {
            const fields = document.querySelectorAll(".select2-container--open .select2-search__field");
            if (!fields || fields.length === 0) return;
            const input = fields[fields.length - 1];
            input.value = "";
            input.dispatchEvent(new Event("input", { bubbles: true }));
            input.focus({ preventScroll: true });
            if (typeof input.setSelectionRange === "function") {
                input.setSelectionRange(0, 0);
            }
        };

        setTimeout(focusOpenSearchField, 0);
        setTimeout(focusOpenSearchField, 30);
    });
});

// =============================================
// ソート変更したら即時並び替える処理
// =============================================
$(function () {
    $("#search_btn").on("click", function () {
        $("#is_search_hidden").val("1");
    });

    $("#sort").on("change", function () {
        $("#sort_hidden").val(this.value);
        $("#is_search_hidden").val("1");
        $("#search_form").submit();
    });
});

// =============================================
// 作物選択時は自動送信して候補を更新
// =============================================
$(function () {
    const $crop = $("#crop");
    const $form = $("#search_form");
    if ($crop.length === 0 || $form.length === 0) return;

    let cropChanged = false;
    $crop.on("change", function () {
        cropChanged = true;
    });

    $crop.on("select2:close", function () {
        if (!cropChanged) return;
        cropChanged = false;
        $("#is_search_hidden").val("");
        $form.submit();
    });
});

// =============================================
// カテゴリ切り替えで対象プルダウン表示を制御
// =============================================
$(function () {
    const $form = $("#search_form");
    const $categoryInputs = $(".js-category");
    const groupMap = {
        "殺虫剤": {
            show: ".target_group_insect",
            clear: ["#disease", "#weed"]
        },
        "殺菌剤": {
            show: ".target_group_disease",
            clear: ["#insect", "#weed"]
        },
        "除草剤": {
            show: ".target_group_weed",
            clear: ["#insect", "#disease"]
        }
    };

    if ($form.length === 0 || $categoryInputs.length === 0) return;

    function applyCategoryTargetVisibility(categoryValue) {
        $(".target_group_insect, .target_group_disease, .target_group_weed").addClass("target_group_hidden");

        const config = groupMap[categoryValue];
        if (!config) return;

        $(config.show).removeClass("target_group_hidden");
    }

    function clearSelectValue(selector) {
        const $select = $(selector);
        if ($select.length === 0) return;

        $select.val(null).trigger("change.select2");
    }

    applyCategoryTargetVisibility($categoryInputs.filter(":checked").val());

    $categoryInputs.on("change", function () {
        const selectedCategory = $(this).val();
        const config = groupMap[selectedCategory];

        applyCategoryTargetVisibility(selectedCategory);

        if (config) {
            config.clear.forEach(function (selector) {
                clearSelectValue(selector);
            });
        }

        $("#is_search_hidden").val("");
        $form.submit();
    });
});

// =============================================
// 検索結果カード作物・病害虫一覧 閉じる処理
// =============================================
$(function () {
    $(document).on("click", ".card_detail .detail_body", function () {
        const $details = $(this).closest("details");
        if ($details.prop("open")) {
            $details.prop("open", false);
        }
    });
});

// =============================================
// 検索タブ切り替え
// =============================================
$(function () {
    const $tabButtons = $(".search_tab_btn");
    if ($tabButtons.length === 0) return;

    $tabButtons.on("click", function () {
        const mode = $(this).data("search-tab");
        if (!mode) return;

        const url = new URL(window.location.href);
        url.search = "";
        url.searchParams.set("search_mode", String(mode));
        window.location.href = url.toString();
    });
});

// =============================================
// 詳細検索 リセット
// =============================================
$(function () {
    const $resetBtn = $("#detail_reset_btn");
    if ($resetBtn.length === 0) return;

    $resetBtn.on("click", function () {
        const url = new URL(window.location.href);
        url.searchParams.delete("detail_crop");
        url.searchParams.set("search_mode", "detail");
        window.location.href = url.toString();
    });
});

// =============================================
// お気に入りハートボタンクリック処理(POST、色切り替え)
// =============================================
$(function () {
    $(document).on("click", ".fav_btn", async function () {
        const $btn = $(this);
        const reg = $btn.data("reg");

        //連打防止
        if ($btn.data("busy")) return;
        $btn.data("busy", true);

        try {
            const res = await fetch("./favorite_toggle.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8" },
                body: new URLSearchParams({ reg: String(reg) }),
            });

            const data = await res.json();

            if (!res.ok || !data.ok) {
                if (data.error === "not_logged_in") {
                    console.log("toggle error:", res.status, data);
                    window.location.href = "./login.php";
                    return;
                }
                alert("エラー:" + (data.error ?? "unknown"));
                return;
            }

            $btn.toggleClass("is-on", !!data.fav);
                if (!data.fav && $btn.data("remove-on-off")) {
                    $btn.closest(".fav_item").remove();
                }
            $btn.attr("aria-pressed", data.fav ? "true" : "false");
        }
        catch (e) {
            console.error(e);
            alert("通信に失敗しました。");
        }
        finally {
            $btn.data("busy", false);
        }
    });
});
