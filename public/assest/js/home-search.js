(function(){
  var input = document.getElementById('homeSearchInput');
  var form = document.getElementById('homeSearchForm');
  var wrapper = document.querySelector('.home-search-wrap');
  var results = document.getElementById('homeSearchResults');
  var catalogElement = document.getElementById('homeSearchCatalog');
  var grid = document.getElementById('homeCourses');
  var empty = document.getElementById('homeSearchEmpty');
  if(!input || !form || !wrapper || !results || !catalogElement || !grid || !empty) return;
  var cards = Array.from(grid.querySelectorAll('.book-section-card'));
  var searchItems = [];
  var fullCatalogLoaded = false;
  var fullCatalogLoading = false;

  try { searchItems = JSON.parse(catalogElement.textContent || '[]'); } catch(error) {}

  function ensureFullCatalog(){
    if(fullCatalogLoaded || fullCatalogLoading) return;
    fullCatalogLoading = true;
    fetch('api/search-catalog.php', { credentials: 'same-origin' })
      .then(function(response){ return response.ok ? response.json() : null; })
      .then(function(items){
        if(Array.isArray(items) && items.length){
          searchItems = items;
          fullCatalogLoaded = true;
          if(input.value.trim()) renderResults();
        }
      })
      .catch(function(){})
      .finally(function(){ fullCatalogLoading = false; });
  }

  function normalize(value){
    return String(value || '').toLocaleLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[‘’`]/g, "'");
  }

  function matchesFor(query){
    if(!query) return [];
    return searchItems.filter(function(item){ return normalize(item.search).includes(query); });
  }

  function filterSections(query){
    var visible = cards.filter(function(card){
      var match = normalize(card.dataset.search || card.textContent).includes(query);
      card.hidden = !match;
      return match;
    });
    return visible;
  }

  function renderResults(){
    var query = normalize(input.value.trim());
    if(query && !fullCatalogLoaded) ensureFullCatalog();
    var matches = matchesFor(query);
    filterSections(query);
    results.replaceChildren();
    results.hidden = query === '';
    empty.hidden = query === '' || matches.length > 0;
    input.setAttribute('aria-expanded', String(query !== ''));
    if(query === '') return matches;
    if(!matches.length){
      empty.textContent = fullCatalogLoading ? 'Masalalar qidirilmoqda...' : 'Mavzu yoki masala topilmadi.';
      results.appendChild(empty);
      return matches;
    }
    matches.slice(0, 8).forEach(function(item, index){
      var link = document.createElement('a');
      link.className = 'home-search-result';
      link.href = typeof item.href === 'string' && item.href.indexOf('?page=') === 0 ? item.href : '?page=home';
      link.setAttribute('role', 'option');
      link.setAttribute('aria-selected', 'false');
      link.id = 'homeSearchResult' + index;
      var icon = document.createElement('span');
      icon.className = 'home-search-result-icon ' + (item.kind === 'Masala' ? 'problem' : 'topic');
      icon.innerHTML = item.kind === 'Masala' ? '<i class="fas fa-code" aria-hidden="true"></i>' : '<i class="fas fa-book-open" aria-hidden="true"></i>';
      var copy = document.createElement('span');
      copy.className = 'home-search-result-copy';
      var title = document.createElement('strong');
      title.textContent = item.number ? item.number + ' · ' + item.title : item.title;
      var context = document.createElement('small');
      context.textContent = item.kind + ' · ' + item.context;
      copy.appendChild(title);
      copy.appendChild(context);
      var arrow = document.createElement('i');
      arrow.className = 'fas fa-arrow-up-right-from-square home-search-result-arrow';
      arrow.setAttribute('aria-hidden', 'true');
      link.appendChild(icon);
      link.appendChild(copy);
      link.appendChild(arrow);
      results.appendChild(link);
    });
    if(matches.length > 8){
      var more = document.createElement('p');
      more.className = 'home-search-more';
      more.textContent = 'Yana ' + (matches.length - 8) + ' ta natija';
      results.appendChild(more);
    }
    return matches;
  }

  input.addEventListener('input', renderResults);
  input.addEventListener('focus', function(){
    ensureFullCatalog();
    if(input.value.trim()) renderResults();
  });
  form.addEventListener('submit', function(event){
    event.preventDefault();
    var matches = renderResults();
    if(matches.length && matches[0].href) window.location.href = matches[0].href;
    else {
      var visible = cards.filter(function(card){ return !card.hidden; });
      if(visible.length === 1) window.location.href = visible[0].href;
    }
  });
  document.addEventListener('keydown', function(event){
    if((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k'){
      event.preventDefault();
      input.focus();
    }
    if(event.key === 'Escape' && document.activeElement === input){
      input.value = '';
      renderResults();
      input.blur();
    }
  });
  document.addEventListener('pointerdown', function(event){
    if(!wrapper.contains(event.target)){
      results.hidden = true;
      input.setAttribute('aria-expanded', 'false');
    }
  });
})();
