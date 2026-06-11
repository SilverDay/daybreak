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

    // CSP-safe confirm dialogs: forms with data-confirm="message" prompt before submit.
    // Replaces inline onsubmit handlers which are blocked by script-src 'self'.
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            var msg = form.getAttribute('data-confirm');
            if (msg && !window.confirm(msg)) {
                e.preventDefault();
            }
        });
    });
});
