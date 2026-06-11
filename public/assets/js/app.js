document.addEventListener('DOMContentLoaded', function () {
    var sel = document.getElementById('window-days');
    if (sel) {
        sel.addEventListener('change', function () { this.form.submit(); });
    }
});
