(function(){
  document.addEventListener('change', function(e){
    var t = e.target;
    if (!t) return;

    if (t.matches && t.matches('[data-select-all="bulk-members"]')) {
      var root = t.closest('form') || document;
      var items = root.querySelectorAll('[data-bulk-member]');
      items.forEach(function(cb){ cb.checked = t.checked; });
    }
  });
})();
