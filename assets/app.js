(function () {
  var q = document.getElementById('q');
  if (q) {
    q.addEventListener('input', function () {
      var v = q.value.trim().toLowerCase();
      var rows = document.querySelectorAll('#rows tr');
      var any = false;
      rows.forEach(function (tr) {
        var hide = v !== '' && tr.textContent.toLowerCase().indexOf(v) === -1;
        tr.hidden = hide;
        if (!hide) any = true;
      });
      var em = document.getElementById('empty');
      if (em) em.hidden = any;
    });
  }
  document.querySelectorAll('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (e) {
      if (!confirm(f.getAttribute('data-confirm'))) e.preventDefault();
    });
  });
})();
