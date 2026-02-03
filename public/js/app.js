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
    $('.js-select2').select2({
        width: '100%',
        placeholder: '指定なし',
        allowClear: true,
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

// =============================================
// ソート変更したら即時並び替える処理
// =============================================
$(function () {
    $("#sort").on("change", function () {
        $("#sort_hidden").val(this.value);
        $("#search_form").submit();
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