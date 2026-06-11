document.addEventListener('DOMContentLoaded', function () {
    // Auto-submit time-window dropdown on change.
    var sel = document.getElementById('window-days');
    if (sel) {
        sel.addEventListener('change', function () { this.form.submit(); });
    }

    // Sources page: select-all / deselect-all buttons.
    var selectAll   = document.getElementById('select-all');
    var deselectAll = document.getElementById('deselect-all');
    function setAll(checked) {
        document.querySelectorAll('input[name="sources[]"]').forEach(function (cb) {
            cb.checked = checked;
        });
    }
    if (selectAll)   { selectAll.addEventListener('click',   function () { setAll(true);  }); }
    if (deselectAll) { deselectAll.addEventListener('click', function () { setAll(false); }); }
});
