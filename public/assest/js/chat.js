(function(){
  var storageKey = 'codestep.ai-chat.conversations.v1';
  var maxConversations = 20;
  var maxMessages = 60;
  var store = { activeId: null, conversations: [] };
  try {
    var saved = JSON.parse(localStorage.getItem(storageKey) || 'null');
    if(saved && Array.isArray(saved.conversations)){
      store.conversations = saved.conversations.filter(function(conversation){
        return conversation && typeof conversation.id === 'string' && Array.isArray(conversation.messages);
      }).slice(0, maxConversations);
      store.activeId = typeof saved.activeId === 'string' ? saved.activeId : null;
    }
  } catch(error) {}

  var wrap = document.createElement('div');
  wrap.id = 'aiChatWrap';
  wrap.innerHTML = '<button id="aiChatBtn" title="AI yordamchini ochish" aria-label="AI yordamchini ochish">AI</button><section id="aiChatBox" style="display:none" aria-label="AI yordamchi bilan suhbat"><header id="aiChatHeader"><div class="info"><div class="avatar" aria-hidden="true">AI</div><div><div class="title">AI Yordamchi</div><div class="subtitle">Masalalar bo\'yicha yordam</div></div></div><div class="ai-chat-tools"><button id="aiChatHistoryToggle" class="header-tool" type="button" title="Suhbatlar tarixi" aria-label="Suhbatlar tarixi"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3a9 9 0 1 1-8.05 4.97l1.78.92A7 7 0 1 0 12 5V2l4 4-4 4V7a5 5 0 1 0 5 5h2a7 7 0 1 1-7-7v-2Zm-1 4h2v5l3 2-1 1.66-4-2.66V7Z"/></svg></button><button id="aiChatNew" class="header-tool" type="button" title="Yangi suhbat" aria-label="Yangi suhbat"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M11 5h2v6h6v2h-6v6h-2v-6H5v-2h6z"/></svg></button><button class="close" id="aiChatClose" title="Yopish" aria-label="Chatni yopish">&times;</button></div></header><aside id="aiChatHistory" hidden aria-label="Suhbatlar tarixi"><div class="history-heading"><div class="history-title">Suhbatlar</div><button id="aiChatClearHistory" type="button">Hammasini o‘chirish</button></div><div id="aiChatHistoryList"></div></aside><div id="aiChatMsgs" aria-live="polite"></div><div id="aiChatInputRow"><div id="aiChatInputWrap"><button id="aiChatAttach" type="button" title="Fayl biriktirish" aria-label="Fayl biriktirish" disabled>⌁</button><input id="aiChatInput" placeholder="Savolingizni yozing..." aria-label="Savolingizni yozing"></div><button id="aiChatSend" type="button"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.4 11.2 21 3.4c.8-.3 1.6.5 1.3 1.3l-7.8 18.2c-.3.8-1.5.8-1.8 0l-2.8-7.2-7.5-2.7c-.8-.3-.8-1.5 0-1.8Zm8.7 2.6 2.3 5.9 5.6-13.1-7.9 7.2Zm-1.3-1.4 7.9-7.2-13.1 5.6 5.2 1.6Z"/></svg><span>Yuborish</span></button></div></section>';
  document.body.appendChild(wrap);
  var btn = document.getElementById('aiChatBtn');
  var box = document.getElementById('aiChatBox');
  var msgs = document.getElementById('aiChatMsgs');
  var input = document.getElementById('aiChatInput');
  var send = document.getElementById('aiChatSend');
  var close = document.getElementById('aiChatClose');
  var newChat = document.getElementById('aiChatNew');
  var historyToggle = document.getElementById('aiChatHistoryToggle');
  var historyPanel = document.getElementById('aiChatHistory');
  var historyList = document.getElementById('aiChatHistoryList');
  var clearHistory = document.getElementById('aiChatClearHistory');
  var busy = false;

  function persist(){
    store.conversations.sort(function(a, b){ return b.updatedAt - a.updatedAt; });
    store.conversations = store.conversations.slice(0, maxConversations);
    try { localStorage.setItem(storageKey, JSON.stringify(store)); } catch(error) {}
  }
  function findConversation(id){
    return store.conversations.find(function(conversation){ return conversation.id === id; }) || null;
  }
  function activeConversation(){ return findConversation(store.activeId); }
  function createConversation(){
    var conversation = {
      id: Date.now().toString(36) + Math.random().toString(36).slice(2, 8),
      title: 'Yangi suhbat',
      updatedAt: Date.now(),
      messages: []
    };
    store.conversations.unshift(conversation);
    store.activeId = conversation.id;
    persist();
    historyPanel.hidden = true;
    renderConversation();
    renderHistory();
    return conversation;
  }
  function setActiveConversation(id){
    if(!findConversation(id)) return;
    store.activeId = id;
    persist();
    historyPanel.hidden = true;
    renderConversation();
    input.focus();
  }
  function deleteConversation(id){
    if(busy) return;
    store.conversations = store.conversations.filter(function(conversation){ return conversation.id !== id; });
    if(store.activeId === id){
      var next = store.conversations.slice().sort(function(a, b){ return b.updatedAt - a.updatedAt; })[0];
      store.activeId = next ? next.id : null;
    }
    persist();
    renderHistory();
    renderConversation();
  }
  function deleteAllConversations(){
    if(busy || !store.conversations.length) return;
    if(!window.confirm('Barcha suhbatlar tarixini o‘chirishni tasdiqlaysizmi?')) return;
    store.conversations = [];
    store.activeId = null;
    persist();
    renderHistory();
    renderConversation();
  }
  function renderHistory(){
    historyList.replaceChildren();
    var conversations = store.conversations.slice().sort(function(a, b){ return b.updatedAt - a.updatedAt; });
    if(!conversations.length){
      var empty = document.createElement('p');
      empty.className = 'history-empty';
      empty.textContent = 'Hozircha suhbatlar yo‘q.';
      historyList.appendChild(empty);
      return;
    }
    conversations.forEach(function(conversation){
      var entry = document.createElement('div');
      entry.className = 'ai-chat-history-row';
      var item = document.createElement('button');
      item.type = 'button';
      item.className = 'ai-chat-history-item' + (conversation.id === store.activeId ? ' active' : '');
      item.dataset.conversationId = conversation.id;
      var title = document.createElement('span');
      title.className = 'history-item-title';
      title.textContent = conversation.title || 'Yangi suhbat';
      var date = document.createElement('time');
      date.className = 'history-item-date';
      date.textContent = new Date(conversation.updatedAt).toLocaleDateString('uz-UZ');
      item.appendChild(title);
      item.appendChild(date);
      item.addEventListener('click', function(){ setActiveConversation(conversation.id); });
      var remove = document.createElement('button');
      remove.type = 'button';
      remove.className = 'ai-chat-history-delete';
      remove.title = 'Suhbatni o‘chirish';
      remove.setAttribute('aria-label', 'Suhbatni o‘chirish: ' + (conversation.title || 'Yangi suhbat'));
      remove.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 3h6l1 2h5v2H3V5h5l1-2Zm-3 6h12l-1 12H7L6 9Zm3 2v8h2v-8H9Zm4 0v8h2v-8h-2Z"/></svg>';
      remove.addEventListener('click', function(event){ event.stopPropagation(); deleteConversation(conversation.id); });
      entry.appendChild(item);
      entry.appendChild(remove);
      historyList.appendChild(entry);
    });
  }
  function renderConversation(){
    msgs.replaceChildren();
    showWelcome();
    var conversation = activeConversation();
    if(!conversation) return;
    conversation.messages.forEach(function(message){
      row(message.text, message.role === 'user' ? 'user' : 'bot', !!message.error);
    });
    msgs.scrollTop = msgs.scrollHeight;
  }
  function saveMessage(conversationId, text, who, isError){
    var conversation = findConversation(conversationId);
    if(!conversation) return;
    conversation.messages.push({
      role: who === 'user' ? 'user' : 'assistant',
      text: text,
      error: !!isError
    });
    if(conversation.messages.length > maxMessages) conversation.messages = conversation.messages.slice(-maxMessages);
    if(who === 'user' && conversation.title === 'Yangi suhbat'){
      conversation.title = text.length > 42 ? text.slice(0, 39) + '...' : text;
    }
    conversation.updatedAt = Date.now();
    persist();
    if(store.activeId === conversationId) row(text, who, isError);
    if(!historyPanel.hidden) renderHistory();
  }
  function setBusy(value){
    busy = value;
    send.disabled = value;
    input.disabled = value;
    newChat.disabled = value;
    historyToggle.disabled = value;
  }

  btn.addEventListener('click', function(){
    box.style.display = box.style.display === 'none' ? 'flex' : 'none';
    if(box.style.display === 'flex'){
      if(!activeConversation()) createConversation();
      else renderConversation();
      input.focus();
    }
  });
  close.addEventListener('click', function(){ box.style.display = 'none'; });
  newChat.addEventListener('click', function(){ if(!busy) createConversation(); });
  historyToggle.addEventListener('click', function(){
    if(busy) return;
    renderHistory();
    historyPanel.hidden = !historyPanel.hidden;
  });
  clearHistory.addEventListener('click', deleteAllConversations);

  function avatar(who){
    var element = document.createElement('div');
    element.className = 'ai-avatar ' + who;
    element.setAttribute('aria-hidden', 'true');
    return element;
  }
  function showWelcome(){
    var welcome = document.createElement('div');
    welcome.className = 'ai-welcome';
    welcome.appendChild(avatar('bot'));
    var message = document.createElement('div');
    message.className = 'ai-message';
    var label = document.createElement('div');
    label.className = 'ai-name';
    label.textContent = 'AI';
    var bubble = document.createElement('div');
    bubble.className = 'ai-bubble bot';
    var title = document.createElement('h4');
    title.textContent = 'Salom!';
    bubble.appendChild(title);
    bubble.appendChild(document.createTextNode('Men CodeStep AI yordamchisiman. Masalalarni yechishda yo\'l-yo\'riq va maslahat beraman, lekin tayyor kod yozib bermayman.'));
    message.appendChild(label);
    message.appendChild(bubble);
    welcome.appendChild(message);
    msgs.appendChild(welcome);
  }
  function appendInlineMarkdown(container, text){
    var pattern = /(`[^`\n]+`|\[[^\]]+\]\(https?:\/\/[^)\s]+\)|\*\*[^*\n]+\*\*|\*[^*\n]+\*)/g;
    var cursor = 0;
    var match;
    while((match = pattern.exec(text)) !== null){
      if(match.index > cursor) container.appendChild(document.createTextNode(text.slice(cursor, match.index)));
      var token = match[0];
      var node;
      var link = token.match(/^\[([^\]]+)\]\((https?:\/\/[^)\s]+)\)$/);
      if(link){
        node = document.createElement('a');
        node.href = link[2];
        node.target = '_blank';
        node.rel = 'noopener noreferrer';
        node.textContent = link[1];
      } else if(token.charAt(0) === '`'){
        node = document.createElement('code');
        node.textContent = token.slice(1, -1);
      } else if(token.slice(0, 2) === '**'){
        node = document.createElement('strong');
        node.textContent = token.slice(2, -2);
      } else {
        node = document.createElement('em');
        node.textContent = token.slice(1, -1);
      }
      container.appendChild(node);
      cursor = pattern.lastIndex;
    }
    if(cursor < text.length) container.appendChild(document.createTextNode(text.slice(cursor)));
  }
  function renderMarkdown(text, container){
    var lines = text.replace(/\r\n?/g, '\n').split('\n');
    var paragraph = [];
    var list = null;
    var codeLines = null;
    function flushParagraph(){
      if(!paragraph.length) return;
      var element = document.createElement('p');
      paragraph.forEach(function(line, index){
        if(index) element.appendChild(document.createElement('br'));
        appendInlineMarkdown(element, line);
      });
      container.appendChild(element);
      paragraph = [];
    }
    function flushList(){
      if(list){ container.appendChild(list); list = null; }
    }
    function flushCode(){
      var pre = document.createElement('pre');
      var code = document.createElement('code');
      code.textContent = codeLines.join('\n');
      pre.appendChild(code);
      container.appendChild(pre);
      codeLines = null;
    }
    lines.forEach(function(line){
      if(codeLines){
        if(/^\s*```\s*$/.test(line)) flushCode();
        else codeLines.push(line);
        return;
      }
      if(/^\s*```[\w+#.-]*\s*$/.test(line)){
        flushParagraph();
        flushList();
        codeLines = [];
        return;
      }
      if(/^\s*$/.test(line)){
        flushParagraph();
        flushList();
        return;
      }
      var heading = line.match(/^\s*(#{1,3})\s+(.+)$/);
      if(heading){
        flushParagraph();
        flushList();
        var headingElement = document.createElement(heading[1].length === 1 ? 'h3' : 'h4');
        appendInlineMarkdown(headingElement, heading[2]);
        container.appendChild(headingElement);
        return;
      }
      var listItem = line.match(/^\s*([-*]|\d+[.)])\s+(.+)$/);
      if(listItem){
        flushParagraph();
        var ordered = /^\d/.test(listItem[1]);
        var tag = ordered ? 'ol' : 'ul';
        if(!list || list.tagName.toLowerCase() !== tag){
          flushList();
          list = document.createElement(tag);
        }
        var item = document.createElement('li');
        appendInlineMarkdown(item, listItem[2]);
        list.appendChild(item);
        return;
      }
      var quote = line.match(/^\s*>\s?(.*)$/);
      if(quote){
        flushParagraph();
        flushList();
        var blockquote = document.createElement('blockquote');
        appendInlineMarkdown(blockquote, quote[1]);
        container.appendChild(blockquote);
        return;
      }
      flushList();
      paragraph.push(line);
    });
    if(codeLines) flushCode();
    flushParagraph();
    flushList();
  }
  function row(text, who, isError){
    var element = document.createElement('div');
    element.className = 'ai-msg-row ' + who;
    element.appendChild(avatar(who));
    var message = document.createElement('div');
    message.className = 'ai-message';
    var label = document.createElement('div');
    label.className = 'ai-name';
    label.textContent = who === 'bot' ? 'AI' : 'Siz';
    var bubble = document.createElement('div');
    bubble.className = 'ai-bubble ' + who + (who === 'bot' ? ' ai-bot' : '') + (isError ? ' error' : '');
    if(who === 'bot' && !isError) renderMarkdown(text, bubble);
    else bubble.textContent = text;
    message.appendChild(label);
    message.appendChild(bubble);
    element.appendChild(message);
    msgs.appendChild(element);
    msgs.scrollTop = msgs.scrollHeight;
    return element;
  }
  function typing(){
    var element = document.createElement('div');
    element.className = 'ai-msg-row bot';
    element.appendChild(avatar('bot'));
    var indicator = document.createElement('div');
    indicator.className = 'ai-typing';
    indicator.innerHTML = '<span></span><span></span><span></span>';
    element.appendChild(indicator);
    msgs.appendChild(element);
    msgs.scrollTop = msgs.scrollHeight;
    return element;
  }
  function apiUrl(){
    var path = window.location.pathname;
    var base = path.substring(0, path.lastIndexOf('/') + 1);
    return base + 'index.php?api=chat';
  }
  function ask(){
    var message = input.value.trim();
    var conversation = activeConversation();
    if(!message || busy) return;
    if(!conversation) conversation = createConversation();
    var conversationId = conversation.id;
    var history = conversation.messages.slice(-12).map(function(item){
      return { role: item.role, content: item.text };
    });
    saveMessage(conversationId, message, 'user', false);
    input.value = '';
    setBusy(true);
    var indicator = typing();
    var controller = new AbortController();
    var timedOut = false;
    var timer = setTimeout(function(){ timedOut = true; controller.abort(); }, 40000);
    fetch(apiUrl(),{
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify({message:message,history:history}),
      credentials:'same-origin',
      signal:controller.signal
    }).then(function(response){
      return response.text().then(function(text){
        var data = null;
        try { data = JSON.parse(text); } catch(error) {}
        if(!response.ok){
          var serverMessage = data && typeof data.reply === 'string' ? data.reply.trim() : '';
          throw new Error(serverMessage || 'AI xizmati xatolik bilan javob berdi (HTTP ' + response.status + ').');
        }
        if(!data || typeof data.reply !== 'string' || !data.reply.trim()) throw new Error('AI bo‘sh javob qaytardi. Savolingizni qisqaroq yozib, qayta urinib ko‘ring.');
        return data.reply.trim();
      });
    }).then(function(reply){
      indicator.remove();
      saveMessage(conversationId, reply, 'bot', false);
    }).catch(function(error){
      indicator.remove();
      var text = timedOut ? 'AI javobi kutilganidan uzoq davom etdi. Qayta urinib ko\'ring.' : (error.name === 'AbortError' ? 'AI javobi kutilganidan uzoq davom etdi. Qayta urinib ko\'ring.' : (error instanceof TypeError ? 'Ulanib bo\'lmadi. Internet ulanishini tekshiring va qayta urinib ko\'ring.' : error.message));
      saveMessage(conversationId, text, 'bot', true);
    }).then(function(){
      clearTimeout(timer);
      setBusy(false);
      input.focus();
    });
  }
  send.addEventListener('click', ask);
  input.addEventListener('keydown', function(event){ if(event.key === 'Enter') ask(); });
})();
