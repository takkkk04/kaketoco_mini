// =============================================
// ユーザー情報変更ボタンを出し入れする関数
// =============================================
$(function() {
    const $form = $("#profile_form");
    if ($form.length === 0) return;

    const $inputs = $form.find("input[type='text']");
    const $editBtn = $("#edit_btn");
    const $saveBtn = $("#save_btn");
    const $cancelBtn = $("#cancel_btn");

    function setEditMode(on) {
        $inputs.prop("disabled", !on);
        $saveBtn.prop("hidden", !on);
        $cancelBtn.prop("hidden", !on);
        $editBtn.prop("hidden", on);
    }

    function resetValues() {
        $inputs.each(function() {
            const $el = $(this);
            const initial = $el.attr("data-initial") ?? "";
            $el.val(initial);
        });
    }

    $editBtn.on("click", function() {
        setEditMode(true);
        $inputs.first().trigger("focus");
    });

    $cancelBtn.on("click", function() {
        resetValues();
        setEditMode(false);
    });

    setEditMode(false);
});