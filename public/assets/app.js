(function () {
  const csrf = (document.getElementById('csrf') || {}).value || '';

  async function postJSON(url, data) {
    const r = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(Object.assign({ csrf }, data)),
    });
    let j = {};
    try { j = await r.json(); } catch (e) {}
    return { http: r.status, body: j };
  }

  function setStatus(el, msg, ok) {
    if (!el) return;
    el.textContent = msg;
    el.style.color = ok ? 'var(--ok)' : 'var(--err)';
  }

  const conversation = document.querySelector('.conversation-page');
  if (conversation) {
    const id = conversation.dataset.id;
    const status = conversation.querySelector('.act-status');
    const textArea = conversation.querySelector('.draft-text');

    conversation.querySelectorAll('.quick-btn').forEach((btn) => {
      btn.addEventListener('click', () => {
        const template = btn.dataset.template || '';
        if (!template) return;
        const current = textArea.value.trim();
        textArea.value = current ? current + '\n\n' + template : template;
        textArea.focus();
      });
    });

    const aiBtn = conversation.querySelector('.act-ai');
    if (aiBtn) aiBtn.addEventListener('click', async () => {
      aiBtn.disabled = true;
      setStatus(status, 'Génération IA...', true);
      const res = await postJSON('actions/ai_draft.php', { id });
      aiBtn.disabled = false;
      if (res.body.ok) {
        textArea.value = res.body.reply || '';
        setStatus(status, res.body.requires_validation ? 'Brouillon généré, à relire' : 'Brouillon généré', true);
      } else {
        setStatus(status, 'Échec IA : ' + (res.body.error || res.http), false);
      }
    });

    const improveBtn = conversation.querySelector('.act-improve');
    if (improveBtn) improveBtn.addEventListener('click', async () => {
      const text = textArea.value.trim();
      if (!text) { setStatus(status, 'Écris une réponse à améliorer', false); return; }
      improveBtn.disabled = true;
      setStatus(status, 'Amélioration IA...', true);
      const res = await postJSON('actions/improve_reply.php', { id, text });
      improveBtn.disabled = false;
      if (res.body.ok) {
        textArea.value = res.body.reply || '';
        setStatus(status, res.body.requires_validation ? 'Réponse améliorée, à relire' : 'Réponse améliorée', true);
      } else {
        setStatus(status, 'Échec IA : ' + (res.body.error || res.http), false);
      }
    });

    const sendBtn = conversation.querySelector('.act-send');
    if (sendBtn) sendBtn.addEventListener('click', async () => {
      const text = textArea.value.trim();
      if (!text) { setStatus(status, 'Message vide', false); return; }
      sendBtn.disabled = true;
      setStatus(status, 'Envoi...', true);
      const res = await postJSON('actions/send.php', { id, text });
      if (res.body.ok) {
        setStatus(status, 'Envoyé', true);
        window.location.href = 'index.php';
      } else {
        sendBtn.disabled = false;
        setStatus(status, 'Échec : ' + (res.body.error || res.http), false);
      }
    });

    const ignoreBtn = conversation.querySelector('.act-ignore');
    if (ignoreBtn) ignoreBtn.addEventListener('click', async () => {
      if (!confirm('Ignorer cette conversation ?')) return;
      const res = await postJSON('actions/ignore.php', { id });
      if (res.body.ok) {
        window.location.href = 'index.php';
      } else {
        setStatus(status, 'Échec : ' + (res.body.error || res.http), false);
      }
    });

    window.setTimeout(() => window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' }), 80);
  }

  document.querySelectorAll('.card.prop').forEach((card) => {
    const id = card.dataset.id;
    const status = card.querySelector('.act-status');
    const fields = () => ({
      id,
      provider: card.querySelector('.f-provider').value,
      model: card.querySelector('.f-model').value,
      instruction: card.querySelector('.f-instruction').value,
      key: card.querySelector('.f-key').value,
      active: card.querySelector('.f-active').checked,
      auto: card.querySelector('.f-auto').checked,
    });

    const saveBtn = card.querySelector('.act-save-prop');
    if (saveBtn) saveBtn.addEventListener('click', async () => {
      saveBtn.disabled = true; setStatus(status, 'Enregistrement...', true);
      const res = await postJSON('actions/save_property.php', fields());
      saveBtn.disabled = false;
      if (res.body.ok) {
        setStatus(status, 'Enregistré', true);
        const keyInput = card.querySelector('.f-key');
        if (keyInput.value) { keyInput.value = ''; keyInput.placeholder = '•••• (clé mise à jour)'; }
      } else setStatus(status, 'Échec : ' + (res.body.error || res.http), false);
    });

    const testBtn = card.querySelector('.act-test');
    const testZone = card.querySelector('.test-zone');
    if (testBtn) testBtn.addEventListener('click', () => {
      testZone.style.display = testZone.style.display === 'none' ? 'flex' : 'none';
    });

    const testRun = card.querySelector('.act-test-run');
    if (testRun) testRun.addEventListener('click', async () => {
      const q = card.querySelector('.test-q').value.trim();
      const out = card.querySelector('.test-out');
      if (!q) { out.textContent = 'Saisissez une question.'; return; }
      out.textContent = 'Génération...';
      const f = fields(); f.question = q;
      const res = await postJSON('actions/test_chatbot.php', f);
      if (res.body.ok) {
        const flag = res.body.requires_validation
          ? '\n\nÀ relire' + (res.body.reason ? ' (' + res.body.reason + ')' : '')
          : '\n\nBrouillon simple';
        out.textContent = res.body.reply + flag;
      } else {
        out.textContent = 'Erreur : ' + (res.body.error || res.http);
      }
    });
  });
})();
