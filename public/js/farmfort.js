/* =============================================
   FARMFORT - JavaScript global
   ============================================= */

const FARMFLOW_THEME_KEY = 'farmflow-theme';
const FARMFLOW_SUPPORT_SOUND_KEY = 'farmflow-support-sound';
const FARMFLOW_SUPPORT_REPEAT_ALERT_MS = 30000;
const FARMFLOW_SUPPORT_FAVICON_BLINK_MS = 900;
let farmFlowSupportAudioContext = null;
let farmFlowOriginalTitle = document.title;
let farmFlowSupportTitleTimer = null;
let farmFlowSupportTitleShowAlert = true;
const farmFlowSupportAlertCounts = {};
let farmFlowOriginalFaviconHref = null;
let farmFlowAlertFaviconHref = null;

function getFarmFortTheme() {
  return localStorage.getItem(FARMFLOW_THEME_KEY) || 'light';
}

function applyFarmFortTheme(theme) {
  const selectedTheme = theme === 'dark' ? 'dark' : 'light';
  document.documentElement.setAttribute('data-theme', selectedTheme);

  document.querySelectorAll('#themeToggle, [data-theme-toggle]').forEach(button => {
    const nextTheme = selectedTheme === 'dark' ? 'modo claro' : 'modo escuro';
    const icon = button.querySelector('i');
    button.setAttribute('aria-label', `Ativar ${nextTheme}`);
    button.setAttribute('title', `Ativar ${nextTheme}`);
    button.setAttribute('data-bs-title', `Ativar ${nextTheme}`);
    if (icon) icon.className = selectedTheme === 'dark' ? 'bi bi-sun' : 'bi bi-moon-stars';

    if (window.bootstrap) {
      const activeTooltip = bootstrap.Tooltip.getInstance(button);
      if (activeTooltip) {
        activeTooltip.dispose();
        new bootstrap.Tooltip(button);
      }
    }
  });
}

function toggleFarmFortTheme() {
  const currentTheme = document.documentElement.getAttribute('data-theme') || getFarmFortTheme();
  const nextTheme = currentTheme === 'dark' ? 'light' : 'dark';
  localStorage.setItem(FARMFLOW_THEME_KEY, nextTheme);
  applyFarmFortTheme(nextTheme);
}

function ffMoneyBR(value) {
  return new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  }).format(Number(value || 0));
}

window.ffMoneyBR = ffMoneyBR;

function initFarmFortTheme() {
  applyFarmFortTheme(getFarmFortTheme());
  document.querySelectorAll('#themeToggle, [data-theme-toggle]').forEach(button => {
    button.addEventListener('click', toggleFarmFortTheme);
  });
}

function initModuleRailScroll() {
  const rail = document.querySelector('.module-rail');
  if (!rail || !window.sessionStorage) return;

  const storageKey = 'farmflow-module-rail-scroll';
  const savedPosition = parseInt(sessionStorage.getItem(storageKey) || '0', 10);
  if (!Number.isNaN(savedPosition)) {
    rail.scrollTop = savedPosition;
  }

  const savePosition = () => {
    sessionStorage.setItem(storageKey, String(rail.scrollTop));
  };

  rail.addEventListener('scroll', savePosition, { passive: true });
  rail.querySelectorAll('a').forEach(link => {
    link.addEventListener('click', savePosition);
  });
}

// Confirmacao padrao
function confirmar(msg) {
  return confirm(msg || 'Confirma esta acao?');
}

// Mascara de moeda
function mascaraMoeda(el) {
  let v = el.value.replace(/\D/g, '');
  v = (parseInt(v || '0', 10) / 100).toFixed(2);
  el.value = v.replace('.', ',');
}

// Calcula valor total a partir de qtd x unitario
function calcTotal(idQtd, idUnit, idTotal) {
  const q = parseFloat((document.getElementById(idQtd)?.value || '0').replace(',', '.')) || 0;
  const u = parseFloat((document.getElementById(idUnit)?.value || '0').replace(',', '.')) || 0;
  const t = document.getElementById(idTotal);
  if (t && u > 0) t.value = (q * u).toFixed(2).replace('.', ',');
}

// Inicializa DataTable padrao
function initDataTable(selector, opts) {
  if (typeof window.jQuery === 'undefined') return;
  const $ = window.jQuery;
  if (!$(selector).length || !$.fn.DataTable) return;

  $(selector).each(function () {
    if (this.querySelector('tbody td[colspan], tbody th[colspan]')) return;
    if ($.fn.DataTable.isDataTable && $.fn.DataTable.isDataTable(this)) return;
    const defaultOrder = [[0, 'desc']];

    $(this).DataTable(Object.assign({
      language: { url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/pt-BR.json' },
      order: defaultOrder,
      pageLength: 25,
      responsive: true
    }, opts || {}));
  });
}

// Flash de alerta programatico
function flashMsg(tipo, msg) {
  const div = document.createElement('div');
  div.className = `alert alert-${tipo === 'sucesso' ? 'success' : 'danger'} alert-dismissible fade show`;
  div.innerHTML = `<i class="bi bi-${tipo === 'sucesso' ? 'check-circle' : 'exclamation-circle'} me-2"></i>${msg}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
  const body = document.querySelector('.page-body');
  if (body) body.insertBefore(div, body.firstChild);
  setTimeout(() => div.remove(), 5000);
}

function supportEscape(text) {
  return String(text || '').replace(/[&<>"']/g, char => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;'
  }[char]));
}

const FARMFLOW_SUPPORT_BLOCK_MESSAGE = 'Não é permitido usar palavrões ou xingamentos no chat. Reescreva a mensagem para continuar.';
const FARMFLOW_SUPPORT_MAX_ATTACHMENT_BYTES = 25 * 1024 * 1024;
const FARMFLOW_SUPPORT_ALLOWED_EXTENSIONS = ['png', 'jpg', 'jpeg', 'webp', 'pdf', 'xls', 'xlsx', 'csv', 'xml'];
const FARMFLOW_SUPPORT_BLOCK_PATTERNS = [
  /(^|[^a-z0-9])p+q+p+([^a-z0-9]|$)/u,
  /(^|[^a-z0-9])f+d+p+([^a-z0-9]|$)/u,
  /(^|[^a-z0-9])v+s+f+([^a-z0-9]|$)/u,
  /(^|[^a-z0-9])v+t+n+c+([^a-z0-9]|$)/u,
  /(^|[^a-z0-9])filh[oa]\s+d[ae]\s+puta([^a-z0-9]|$)/u,
  /(^|[^a-z0-9])vai\s+tomar\s+no\s+cu([^a-z0-9]|$)/u,
  /(^|[^a-z0-9])vai\s+se\s+ferrar([^a-z0-9]|$)/u,
  /(^|[^a-z0-9])put[ao]s?([^a-z0-9]|$)/u,
  /(^|[^a-z0-9])putaria([^a-z0-9]|$)/u,
  /(^|[^a-z0-9])porra([^a-z0-9]|$)/u,
  /(^|[^a-z0-9])caralh[oa]([^a-z0-9]|$)/u,
  /(^|[^a-z0-9])merda([^a-z0-9]|$)/u,
  /(^|[^a-z0-9])bosta([^a-z0-9]|$)/u,
  /(^|[^a-z0-9])cuzao([^a-z0-9]|$)/u,
  /(^|[^a-z0-9])babaca([^a-z0-9]|$)/u,
  /(^|[^a-z0-9])idiot[ao]([^a-z0-9]|$)/u,
  /(^|[^a-z0-9])imbecil([^a-z0-9]|$)/u,
  /(^|[^a-z0-9])burr[ao]([^a-z0-9]|$)/u,
  /(^|[^a-z0-9])lixo([^a-z0-9]|$)/u,
  /(^|[^a-z0-9])vagabund[ao]([^a-z0-9]|$)/u,
  /(^|[^a-z0-9])arrombad[ao]([^a-z0-9]|$)/u,
  /(^|[^a-z0-9])desgracad[ao]([^a-z0-9]|$)/u,
  /(^|[^a-z0-9])otari[ao]([^a-z0-9]|$)/u,
  /(^|[^a-z0-9])corno([^a-z0-9]|$)/u
];

function supportNormalizeText(text) {
  return String(text || '')
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/\s+/g, ' ')
    .trim();
}

function supportHasBlockedLanguage(text) {
  const normalized = supportNormalizeText(text);
  return FARMFLOW_SUPPORT_BLOCK_PATTERNS.some(pattern => pattern.test(normalized));
}

function supportRejectBlockedLanguage(text) {
  if (!supportHasBlockedLanguage(text)) return false;
  flashMsg('erro', FARMFLOW_SUPPORT_BLOCK_MESSAGE);
  return true;
}

function supportFetch(endpoint, action, options) {
  const opts = options || {};
  const method = opts.method || 'GET';
  const url = new URL(endpoint, window.location.href);
  const headers = Object.assign({}, opts.headers || {});
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  if (csrfToken && method.toUpperCase() !== 'GET') {
    headers['X-CSRF-TOKEN'] = csrfToken;
  }
  const parts = String(action || '').split('&');
  url.searchParams.set('action', parts.shift() || '');
  if (parts.length) {
    new URLSearchParams(parts.join('&')).forEach((value, key) => url.searchParams.set(key, value));
  }
  return fetch(url.toString(), {
    method,
    body: opts.body || null,
    headers,
    credentials: 'same-origin'
  }).then(response => {
    const contentType = response.headers.get('content-type') || '';
    if (!contentType.includes('application/json')) {
      return { ok: false, erro: response.redirected ? 'Sessão expirada. Entre novamente.' : 'Não foi possível processar a resposta do chat.' };
    }
    return response.text().then(text => {
      const cleanText = text.replace(/^\uFEFF/, '').trim();
      try {
        return JSON.parse(cleanText);
      } catch (e) {
        return { ok: false, erro: 'Não foi possível processar a resposta do chat.' };
      }
    });
  });
}

function supportFormatBytes(bytes) {
  const value = Number(bytes || 0);
  if (value >= 1024 * 1024) return (value / 1024 / 1024).toFixed(1).replace('.', ',') + ' MB';
  if (value >= 1024) return (value / 1024).toFixed(1).replace('.', ',') + ' KB';
  return value + ' B';
}

function supportAttachmentExtension(file) {
  return String(file?.name || '').split('.').pop().toLowerCase();
}

function supportEnsureAttachmentPreview(form) {
  if (!form) return null;
  let box = form.querySelector('[data-support-file-list]');
  if (!box) {
    box = document.createElement('div');
    box.className = 'ff-support-file-list';
    box.setAttribute('data-support-file-list', '');
    form.insertBefore(box, form.firstChild);
  }
  return box;
}

function supportRenderSelectedFiles(form, fileInput) {
  const box = supportEnsureAttachmentPreview(form);
  if (!box) return;
  const files = Array.from(fileInput?.files || []);
  box.hidden = files.length === 0;
  if (!files.length) {
    box.innerHTML = '';
    return;
  }
  box.innerHTML = files.map(file => {
    const ext = supportAttachmentExtension(file);
    const icon = ['png', 'jpg', 'jpeg', 'webp'].includes(ext) ? 'bi-image' : (ext === 'pdf' ? 'bi-filetype-pdf' : 'bi-file-earmark-spreadsheet');
    return `<span><i class="bi ${icon}"></i><strong>${supportEscape(file.name)}</strong><em>${supportEscape(supportFormatBytes(file.size))}</em></span>`;
  }).join('') + '<button type="button" data-support-clear-files title="Remover anexos"><i class="bi bi-x-lg"></i></button>';
}

function supportClipboardImageFiles(event) {
  const items = Array.from(event.clipboardData?.items || []);
  const images = items.filter(item => item.kind === 'file' && String(item.type || '').startsWith('image/'));
  if (!images.length) return [];

  const now = new Date();
  const stamp = [
    now.getFullYear(),
    String(now.getMonth() + 1).padStart(2, '0'),
    String(now.getDate()).padStart(2, '0'),
    '_',
    String(now.getHours()).padStart(2, '0'),
    String(now.getMinutes()).padStart(2, '0'),
    String(now.getSeconds()).padStart(2, '0')
  ].join('');

  return images.map((item, index) => {
    const blob = item.getAsFile();
    if (!blob) return null;
    const mime = blob.type || item.type || 'image/png';
    const ext = mime.includes('jpeg') ? 'jpg' : (mime.split('/')[1] || 'png').replace(/[^a-z0-9]/gi, '').toLowerCase();
    return new File([blob], `print_${stamp}_${index + 1}.${ext || 'png'}`, {
      type: mime,
      lastModified: Date.now()
    });
  }).filter(Boolean);
}

function supportAppendFilesToInput(form, fileInput, files) {
  const pastedFiles = Array.from(files || []);
  if (!form || !fileInput || !pastedFiles.length) return false;
  if (fileInput.disabled) {
    flashMsg('erro', 'Selecione ou assuma uma conversa antes de anexar o print.');
    return false;
  }
  if (!window.DataTransfer) {
    flashMsg('erro', 'Seu navegador não permitiu colar o print como anexo.');
    return false;
  }

  let transfer = null;
  try {
    transfer = new DataTransfer();
  } catch (e) {
    flashMsg('erro', 'Seu navegador não permitiu colar o print como anexo.');
    return false;
  }

  Array.from(fileInput.files || []).forEach(file => transfer.items.add(file));
  pastedFiles.forEach(file => transfer.items.add(file));

  if (!supportValidateAttachmentFiles(Array.from(transfer.files))) return false;

  fileInput.files = transfer.files;
  supportRenderSelectedFiles(form, fileInput);
  flashMsg('sucesso', pastedFiles.length > 1 ? 'Prints anexados. Você já pode enviar.' : 'Print anexado. Você já pode enviar.');
  return true;
}

function supportBindClipboardPaste(form, fileInput) {
  if (!form || !fileInput || form.dataset.supportPasteBound === '1') return;
  form.dataset.supportPasteBound = '1';
  form.addEventListener('paste', event => {
    const pastedImages = supportClipboardImageFiles(event);
    if (!pastedImages.length) return;
    event.preventDefault();
    supportAppendFilesToInput(form, fileInput, pastedImages);
  });
}

function supportBindAttachmentPreview(form, fileInput) {
  if (!form || !fileInput) return;
  const box = supportEnsureAttachmentPreview(form);
  fileInput.addEventListener('change', () => supportRenderSelectedFiles(form, fileInput));
  box?.addEventListener('click', event => {
    const clearButton = event.target.closest('[data-support-clear-files]');
    if (!clearButton) return;
    fileInput.value = '';
    supportRenderSelectedFiles(form, fileInput);
  });
  supportBindClipboardPaste(form, fileInput);
  supportRenderSelectedFiles(form, fileInput);
}

function supportValidateAttachmentFiles(files) {
  const list = Array.from(files || []);
  const tooLarge = list.find(file => file.size > FARMFLOW_SUPPORT_MAX_ATTACHMENT_BYTES);
  if (tooLarge) {
    flashMsg('erro', 'O anexo "' + tooLarge.name + '" passa de 25 MB.');
    return false;
  }
  const invalid = list.find(file => !FARMFLOW_SUPPORT_ALLOWED_EXTENSIONS.includes(supportAttachmentExtension(file)));
  if (invalid) {
    flashMsg('erro', 'Envie apenas print/imagem, PDF, Excel, CSV ou XML.');
    return false;
  }
  return true;
}

function supportClearAttachmentFiles(form, fileInput) {
  if (!fileInput) return;
  fileInput.value = '';
  supportRenderSelectedFiles(form, fileInput);
}

function supportRenderMessages(container, messages, perspective) {
  if (!container) return;
  if (!messages || !messages.length) {
    container.innerHTML = '<div class="ff-support-empty">Nenhuma mensagem nesta conversa.</div>';
    return;
  }
  container.innerHTML = messages.map(message => {
    const mine = perspective === 'admin' ? message.autor_tipo === 'admin' : message.autor_tipo === 'cliente';
    const label = message.autor_tipo === 'admin'
      ? 'Suporte'
      : (message.autor_tipo === 'ia' ? 'Filtro IA' : (message.autor_tipo === 'sistema' ? 'Sistema' : (message.autor_nome || 'Cliente')));
    let readStatus = '';
    if (mine && message.autor_tipo === 'cliente') {
      readStatus = message.lida_admin ? 'Visualizada pelo suporte' : 'Enviada ao suporte';
    } else if (mine && message.autor_tipo === 'admin') {
      readStatus = message.lida_cliente ? 'Visualizada pelo cliente' : 'Aguardando visualização do cliente';
    }
    const attachments = (message.anexos || []).map(file => {
      const status = file.disponivel
        ? `<a href="${supportEscape(file.download_url || '#')}" target="_blank" rel="noopener"><i class="bi bi-download"></i>Baixar</a>`
        : `<span><i class="bi bi-check2-circle"></i>Baixado ${supportEscape(file.baixado_em || '')}</span>`;
      return `<div class="ff-support-attachment"><i class="bi bi-paperclip"></i><strong>${supportEscape(file.nome || 'Anexo')}</strong><em>${supportEscape(supportFormatBytes(file.tamanho))}</em>${status}</div>`;
    }).join('');
    return `<article class="ff-support-message ${mine ? 'mine' : 'theirs'}">
      <small>${supportEscape(label)} - ${supportEscape(message.hora || '')}</small>
      <div>${supportEscape(message.mensagem)}</div>
      ${attachments ? `<section class="ff-support-attachments">${attachments}</section>` : ''}
      ${readStatus ? `<span class="ff-support-read-status"><i class="bi bi-check2-all"></i>${supportEscape(readStatus)}</span>` : ''}
    </article>`;
  }).join('');
  container.scrollTop = container.scrollHeight;
}

function supportIsInternalSupportContext() {
  return !!document.querySelector('[data-support-admin]');
}

function supportSoundEnabled() {
  return localStorage.getItem(FARMFLOW_SUPPORT_SOUND_KEY) !== '0';
}

function supportFaviconLink() {
  let link = document.querySelector('link[data-farmfort-favicon="primary"], link[rel~="icon"]');
  if (!link) {
    link = document.createElement('link');
    link.rel = 'icon';
    link.type = 'image/svg+xml';
    link.dataset.farmfortFavicon = 'primary';
    document.head.appendChild(link);
  }
  if (farmFlowOriginalFaviconHref === null) {
    farmFlowOriginalFaviconHref = link.href || link.getAttribute('href') || '';
  }
  return link;
}

function supportAlertFaviconHref() {
  if (farmFlowAlertFaviconHref) return farmFlowAlertFaviconHref;
  const svg = [
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">',
    '<defs>',
    '<linearGradient id="farmflow-alert-bg" x1="11" y1="8" x2="55" y2="58" gradientUnits="userSpaceOnUse">',
    '<stop stop-color="#ff7b7b"/>',
    '<stop offset="1" stop-color="#dc2626"/>',
    '</linearGradient>',
    '<linearGradient id="farmflow-alert-leaf" x1="18" y1="14" x2="47" y2="49" gradientUnits="userSpaceOnUse">',
    '<stop stop-color="#fff1f2"/>',
    '<stop offset="1" stop-color="#fecaca"/>',
    '</linearGradient>',
    '</defs>',
    '<rect x="6" y="6" width="52" height="52" rx="14" fill="url(#farmflow-alert-bg)"/>',
    '<path d="M17 44c8.5-1.7 15.9-6.3 22-13.7 4.6-5.6 7.4-11.7 8.5-18.3-7.8 2.5-14.8 3.6-21 3.4-6.2-.2-10.8 1.2-13.8 4.2-3.4 3.4-4.8 8.2-4.2 14.4.3 3.1 1.1 6 2.4 8.8-2 1.4-3.9 2.6-5.9 3.6l3.2 6.3c2.9-1.4 5.8-3.2 8.8-5.4z" fill="url(#farmflow-alert-leaf)"/>',
    '<path d="M14.5 43.5c8.8-8.9 18-14.9 27.7-18" fill="none" stroke="#b91c1c" stroke-width="3.2" stroke-linecap="round"/>',
    '<path d="M23 53h20" stroke="#fecaca" stroke-width="3.4" stroke-linecap="round" opacity=".75"/>',
    '</svg>'
  ].join('');
  farmFlowAlertFaviconHref = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg);
  return farmFlowAlertFaviconHref;
}

function supportSetAlertFavicon(active) {
  const link = supportFaviconLink();
  if (!farmFlowOriginalFaviconHref) return;
  link.href = active ? supportAlertFaviconHref() : farmFlowOriginalFaviconHref;
}

function supportAlertNewMessage(options) {
  const alert = options || {};
  if (alert.count) supportSetBrowserTabAlert(alert.count, alert.source || alert.tag || 'global');
  supportPlayTone();
}

function supportUpdateSoundButtons(scope) {
  const enabled = supportSoundEnabled();
  const buttons = (scope || document).querySelectorAll('[data-support-sound]');
  buttons.forEach(button => {
    button.classList.toggle('btn-farmflow', enabled);
    button.classList.toggle('btn-outline-secondary', !enabled);
    button.setAttribute('aria-pressed', enabled ? 'true' : 'false');
    button.setAttribute('title', enabled
      ? 'Som ativado. Clique para desativar os alertas sonoros.'
      : 'Som desativado. Clique para ativar os alertas sonoros.');
    button.innerHTML = `<i class="bi ${enabled ? 'bi-volume-up-fill' : 'bi-volume-mute-fill'} me-1"></i>${enabled ? 'Som ativado' : 'Som desativado'}`;
    button.removeAttribute('data-notification-status');
  });
}

function supportBindSoundButtons(scope) {
  (scope || document).querySelectorAll('[data-support-sound]').forEach(button => {
    if (button.dataset.supportSoundBound === '1') return;
    button.dataset.supportSoundBound = '1';
    button.addEventListener('click', () => {
      const nextEnabled = !supportSoundEnabled();
      localStorage.setItem(FARMFLOW_SUPPORT_SOUND_KEY, nextEnabled ? '1' : '0');
      supportUpdateSoundButtons(document);
      if (nextEnabled) {
        supportPrimeAudio();
        supportPlayTone();
      }
    });
  });
  supportUpdateSoundButtons(scope || document);
}

function supportGetAudioContext() {
  const AudioContextClass = window.AudioContext || window.webkitAudioContext;
  if (!AudioContextClass) return null;
  if (!farmFlowSupportAudioContext) {
    farmFlowSupportAudioContext = new AudioContextClass();
  }
  if (farmFlowSupportAudioContext.state === 'suspended') {
    farmFlowSupportAudioContext.resume().catch(() => {});
  }
  return farmFlowSupportAudioContext;
}

function supportPrimeAudio() {
  if (!supportSoundEnabled()) return;
  try {
    const context = supportGetAudioContext();
    if (!context) return;
    const oscillator = context.createOscillator();
    const gain = context.createGain();
    oscillator.type = 'sine';
    oscillator.frequency.setValueAtTime(440, context.currentTime);
    gain.gain.setValueAtTime(0.0001, context.currentTime);
    oscillator.connect(gain);
    gain.connect(context.destination);
    oscillator.start();
    oscillator.stop(context.currentTime + 0.03);
  } catch (e) {}
}

function supportPlayTone() {
  if (!supportSoundEnabled()) return;
  try {
    const context = supportGetAudioContext();
    if (!context) return;
    const playTone = (frequency, start, duration) => {
      const oscillator = context.createOscillator();
      const gain = context.createGain();
      oscillator.type = 'sine';
      oscillator.frequency.setValueAtTime(frequency, start);
      gain.gain.setValueAtTime(0.001, start);
      gain.gain.exponentialRampToValueAtTime(0.16, start + 0.025);
      gain.gain.exponentialRampToValueAtTime(0.001, start + duration);
      oscillator.connect(gain);
      gain.connect(context.destination);
      oscillator.start(start);
      oscillator.stop(start + duration + 0.02);
    };
    const now = context.currentTime + 0.02;
    playTone(1046.5, now, 0.18);
    playTone(1318.5, now + 0.18, 0.24);
  } catch (e) {}
}

function supportSetBrowserTabAlert(count, source) {
  const alertSource = source || 'global';
  const unread = Number(count || 0);
  if (unread > 0) {
    farmFlowSupportAlertCounts[alertSource] = unread;
  } else {
    delete farmFlowSupportAlertCounts[alertSource];
  }

  const totalUnread = Object.values(farmFlowSupportAlertCounts)
    .reduce((sum, value) => sum + Number(value || 0), 0);

  document.documentElement.classList.toggle('ff-support-tab-alert', totalUnread > 0);
  if (totalUnread <= 0) {
    if (farmFlowSupportTitleTimer) clearInterval(farmFlowSupportTitleTimer);
    farmFlowSupportTitleTimer = null;
    farmFlowSupportTitleShowAlert = true;
    document.title = farmFlowOriginalTitle;
    supportSetAlertFavicon(false);
    return;
  }

  const alertTitle = `(${totalUnread}) Nova mensagem - FarmFort`;
  document.title = farmFlowSupportTitleShowAlert ? alertTitle : farmFlowOriginalTitle;
  supportSetAlertFavicon(farmFlowSupportTitleShowAlert);
  farmFlowSupportTitleShowAlert = !farmFlowSupportTitleShowAlert;
  if (!farmFlowSupportTitleTimer) {
    farmFlowSupportTitleTimer = setInterval(() => {
      const activeTotal = Object.values(farmFlowSupportAlertCounts)
        .reduce((sum, value) => sum + Number(value || 0), 0);
      if (activeTotal <= 0) {
        clearInterval(farmFlowSupportTitleTimer);
        farmFlowSupportTitleTimer = null;
        farmFlowSupportTitleShowAlert = true;
        document.title = farmFlowOriginalTitle;
        supportSetAlertFavicon(false);
        return;
      }
      const activeTitle = `(${activeTotal}) Nova mensagem - FarmFort`;
      document.title = farmFlowSupportTitleShowAlert ? activeTitle : farmFlowOriginalTitle;
      supportSetAlertFavicon(farmFlowSupportTitleShowAlert);
      farmFlowSupportTitleShowAlert = !farmFlowSupportTitleShowAlert;
    }, FARMFLOW_SUPPORT_FAVICON_BLINK_MS);
  }
}

function internalChatRenderMessages(container, messages) {
  if (!container) return;
  if (!messages || !messages.length) {
    container.innerHTML = '<div class="ff-support-empty">Nenhuma mensagem nesta conversa.</div>';
    return;
  }
  container.innerHTML = messages.map(message => {
    const attachments = (message.anexos || []).map(file => {
      const status = file.disponivel
        ? `<a href="${supportEscape(file.download_url || '#')}" target="_blank" rel="noopener"><i class="bi bi-download"></i>Baixar temporario</a>`
        : `<span><i class="bi bi-clock-history"></i>Baixado ou expirado</span>`;
      return `<div class="ff-support-attachment"><i class="bi bi-paperclip"></i><strong>${supportEscape(file.nome || 'Anexo')}</strong><em>${supportEscape(supportFormatBytes(file.tamanho))}</em>${status}</div>`;
    }).join('');
    const readStatus = message.mine ? (message.lida ? 'Visualizada' : 'Enviada') : '';
    return `<article class="ff-support-message ${message.mine ? 'mine' : 'theirs'}">
      <small>${supportEscape(message.mine ? 'Você' : (message.autor_nome || 'Usuário'))} - ${supportEscape(message.hora || '')}</small>
      <div>${supportEscape(message.mensagem || '')}</div>
      ${attachments ? `<section class="ff-support-attachments">${attachments}</section>` : ''}
      ${readStatus ? `<span class="ff-support-read-status"><i class="bi bi-check2-all"></i>${supportEscape(readStatus)}</span>` : ''}
    </article>`;
  }).join('');
  container.scrollTop = container.scrollHeight;
}

function initInternalChat(root) {
  const endpoint = root.dataset.chatEndpoint;
  if (!endpoint) return;

  const usersBox = root.querySelector('[data-internal-users]');
  const messagesBox = root.querySelector('[data-internal-messages]');
  const form = root.querySelector('[data-internal-form]');
  const textarea = form ? form.querySelector('textarea[name="mensagem"]') : null;
  const fileInput = form ? form.querySelector('input[type="file"][name="anexos[]"]') : null;
  const selectedBox = root.querySelector('[data-internal-selected]');
  const selectedTitle = selectedBox ? selectedBox.querySelector('strong') : null;
  const unreadBadges = root.querySelectorAll('[data-internal-total-unread]');
  let selectedUserId = null;
  let peers = [];
  let peersTimer = null;
  let messagesTimer = null;
  let heartbeatTimer = null;
  let lastIncomingMessageId = 0;
  let firstMessagesLoad = true;

  function heartbeatOnline() {
    return supportFetch(endpoint, 'heartbeat');
  }

  function markOffline() {
    const separator = endpoint.includes('?') ? '&' : '?';
    const url = endpoint + separator + 'action=offline';
    try {
      if (navigator.sendBeacon) {
        navigator.sendBeacon(url, new Blob([], { type: 'application/x-www-form-urlencoded' }));
        return;
      }
      fetch(url, { method: 'POST', keepalive: true, credentials: 'same-origin' }).catch(() => {});
    } catch (e) {}
  }

  function setUnreadBadge(total) {
    const value = Number(total || 0);
    unreadBadges.forEach(badge => {
      badge.textContent = String(value);
      badge.hidden = value <= 0;
    });
    if (value > 0) root.classList.add('has-unread');
    else root.classList.remove('has-unread');
    if (value > 0) supportSetBrowserTabAlert(value, 'internal-chat');
    else supportSetBrowserTabAlert(0, 'internal-chat');
  }

  function selectedPeer() {
    return peers.find(peer => Number(peer.id) === Number(selectedUserId)) || null;
  }

  function renderPeers() {
    if (!usersBox) return;
    if (!peers.length) {
      usersBox.innerHTML = '<div class="ff-support-empty">Nenhum usuário da sua fazenda/grupo encontrado.</div>';
      return;
    }
    usersBox.innerHTML = peers.map(peer => {
      const active = Number(peer.id) === Number(selectedUserId);
      const props = (peer.propriedades || []).join(', ');
      return `<button type="button" class="ff-internal-user ${active ? 'active' : ''} ${peer.online ? 'is-online' : 'is-offline'}" data-user-id="${peer.id}">
        <strong>${supportEscape(peer.nome || 'Usuário')}</strong>
        <span class="ff-online-dot" title="${peer.online ? 'Online' : 'Offline'}"></span>
        <small>${supportEscape(peer.online ? 'Online agora' : (peer.ultima_atividade ? 'Visto em ' + peer.ultima_atividade : 'Offline'))}${props ? ' - ' + supportEscape(props) : ''}</small>
        ${Number(peer.unread || 0) > 0 ? `<b>${Number(peer.unread || 0)}</b>` : ''}
      </button>`;
    }).join('');
    usersBox.querySelectorAll('[data-user-id]').forEach(button => {
      button.addEventListener('click', () => selectPeer(Number(button.dataset.userId || 0)));
    });
  }

  function syncSelectedHeader() {
    const peer = selectedPeer();
    if (selectedBox) selectedBox.hidden = !peer;
    if (selectedTitle) selectedTitle.textContent = peer ? (peer.nome || 'Usuário') : 'Selecione um usuário';
    if (textarea) textarea.disabled = !peer;
    if (fileInput) fileInput.disabled = !peer;
    const submitButton = form ? form.querySelector('button[type="submit"]') : null;
    if (submitButton) submitButton.disabled = !peer;
  }

  function loadPeers(playAlert) {
    return supportFetch(endpoint, 'peers').then(data => {
      if (!data.ok) {
        if (usersBox) {
          usersBox.innerHTML = `<div class="ff-support-empty">${supportEscape(data.erro || 'Não foi possível carregar os usuários da propriedade.')}</div>`;
        }
        return;
      }
      const previousUnread = peers.reduce((sum, peer) => sum + Number(peer.unread || 0), 0);
      peers = data.peers || [];
      const totalUnread = Number(data.total_unread || 0);
      if (playAlert && totalUnread > previousUnread) {
        const unreadPeer = peers.find(peer => Number(peer.unread || 0) > 0);
        supportAlertNewMessage({
          title: 'Nova mensagem no chat - FarmFort',
          body: unreadPeer ? `${unreadPeer.nome || 'Usuário'} enviou uma mensagem.` : 'Você recebeu uma nova mensagem no chat.',
          count: totalUnread,
          source: 'internal-chat',
          tag: 'farmflow-chat-interno'
        });
      }
      setUnreadBadge(totalUnread);
      if (selectedUserId && !peers.some(peer => Number(peer.id) === Number(selectedUserId))) {
        selectedUserId = null;
        internalChatRenderMessages(messagesBox, []);
      }
      renderPeers();
      syncSelectedHeader();
    }).catch(() => {
      if (usersBox) usersBox.innerHTML = '<div class="ff-support-empty">Não foi possível carregar os usuários da propriedade.</div>';
    });
  }

  function loadMessages(playAlert) {
    if (!selectedUserId) return Promise.resolve();
    return supportFetch(endpoint, 'messages&usuario_id=' + encodeURIComponent(selectedUserId)).then(data => {
      if (!data.ok) return;
      const messages = data.messages || [];
      const latestIncomingId = Math.max(...messages.filter(message => !message.mine).map(message => Number(message.id || 0)), 0);
      if (playAlert !== false && !firstMessagesLoad && latestIncomingId > lastIncomingMessageId) {
        const peer = selectedPeer();
        supportAlertNewMessage({
          title: 'Nova mensagem no chat - FarmFort',
          body: peer ? `${peer.nome || 'Usuário'} enviou uma mensagem.` : 'Você recebeu uma nova mensagem no chat.',
          count: 1,
          source: 'internal-chat',
          tag: 'farmflow-chat-interno'
        });
      }
      lastIncomingMessageId = Math.max(lastIncomingMessageId, latestIncomingId);
      firstMessagesLoad = false;
      internalChatRenderMessages(messagesBox, messages);
      loadPeers(false);
    });
  }

  function selectPeer(id) {
    selectedUserId = Number(id || 0);
    renderPeers();
    syncSelectedHeader();
    loadMessages(false);
  }

  textarea?.addEventListener('keydown', event => {
    if (event.key === 'Enter' && !event.shiftKey) {
      event.preventDefault();
      form?.requestSubmit();
    }
  });

  form?.addEventListener('submit', event => {
    event.preventDefault();
    const peer = selectedPeer();
    if (!peer) {
      flashMsg('erro', 'Selecione um usuário da fazenda para conversar.');
      return;
    }
    const msg = (textarea?.value || '').trim();
    const files = Array.from(fileInput?.files || []);
    if (!msg && !files.length) return;
    if (supportRejectBlockedLanguage(msg)) return;
    if (!supportValidateAttachmentFiles(files)) return;
    const body = new FormData();
    body.append('destinatario_id', String(peer.id));
    body.append('mensagem', msg);
    files.forEach(file => body.append('anexos[]', file));
    supportFetch(endpoint, 'send', { method: 'POST', body }).then(data => {
      if (!data.ok) {
        flashMsg('erro', data.erro || 'Não foi possível enviar a mensagem.');
        return;
      }
      if (textarea) textarea.value = '';
      supportClearAttachmentFiles(form, fileInput);
      internalChatRenderMessages(messagesBox, data.messages || []);
      loadPeers(false);
    });
  });

  root.addEventListener('farmflow:internal-chat-open', () => {
    loadPeers(false);
    heartbeatOnline();
    if (!peersTimer) peersTimer = setInterval(() => loadPeers(true), 10000);
    if (!messagesTimer) messagesTimer = setInterval(() => loadMessages(true), 7000);
  });

  root.addEventListener('farmflow:internal-chat-close', () => {
    if (messagesTimer) clearInterval(messagesTimer);
    messagesTimer = null;
  });

  syncSelectedHeader();
  supportBindAttachmentPreview(form, fileInput);
  heartbeatOnline();
  if (!heartbeatTimer) heartbeatTimer = setInterval(heartbeatOnline, 25000);
  window.addEventListener('pagehide', markOffline);
  loadPeers(false);
  if (!peersTimer) peersTimer = setInterval(() => loadPeers(true), 5000);
}

function initSupportClient(root) {
  const endpoint = root.dataset.supportEndpoint;
  const toggle = root.querySelector('[data-support-client-toggle]');
  const close = root.querySelector('[data-support-client-close]');
  const panel = root.querySelector('[data-support-client-panel]');
  const messagesBox = root.querySelector('[data-support-client-messages]');
  const form = root.querySelector('[data-support-client-form]');
  const textarea = form ? form.querySelector('textarea[name="mensagem"]') : null;
  const fileInput = form ? form.querySelector('input[type="file"][name="anexos[]"]') : null;
  const soundButtons = root.querySelectorAll('[data-support-sound]');
  const finishButton = root.querySelector('[data-support-client-finish]');
  const keepButton = root.querySelector('[data-support-client-keep]');
  const statusBox = root.querySelector('[data-support-client-status]');
  const tabButtons = root.querySelectorAll('[data-support-client-tab]');
  const tabViews = root.querySelectorAll('[data-support-client-view]');
  let conversaId = null;
  let currentStatus = null;
  let messagePollTimer = null;
  let summaryPollTimer = null;
  let lastAdminMessageId = 0;
  let firstClientSummary = true;
  let activeClientTab = 'interno';

  initInternalChat(root);
  supportBindAttachmentPreview(form, fileInput);

  function setClientTab(tabName) {
    activeClientTab = tabName || 'interno';
    tabButtons.forEach(button => {
      button.classList.toggle('active', button.dataset.supportClientTab === activeClientTab);
    });
    tabViews.forEach(view => {
      view.hidden = view.dataset.supportClientView !== activeClientTab;
    });
    if (activeClientTab === 'interno') {
      root.dispatchEvent(new CustomEvent('farmflow:internal-chat-open'));
      if (messagePollTimer) {
        clearInterval(messagePollTimer);
        messagePollTimer = null;
      }
    } else if (activeClientTab === 'suporte') {
      root.dispatchEvent(new CustomEvent('farmflow:internal-chat-close'));
      loadBoot();
      if (!messagePollTimer) messagePollTimer = setInterval(loadMessages, 7000);
    }
  }

  function updateClientConversationState(data = {}) {
    if (Object.prototype.hasOwnProperty.call(data, 'conversa_id')) {
      conversaId = data.conversa_id ? Number(data.conversa_id) : null;
    }
    if (Object.prototype.hasOwnProperty.call(data, 'status')) {
      currentStatus = data.status || null;
    }
    const pendingClose = currentStatus === 'aguardando_encerramento';
    const closed = currentStatus === 'encerrada';
    if (statusBox) {
      statusBox.hidden = !pendingClose && !closed;
      statusBox.textContent = pendingClose
        ? 'O suporte perguntou se pode finalizar este atendimento. Responda ou clique em Continuar atendimento para manter aberto.'
        : (closed ? 'Atendimento finalizado. Envie uma nova mensagem para abrir outro chamado.' : '');
    }
    if (finishButton) finishButton.hidden = !conversaId || closed;
    if (keepButton) keepButton.hidden = !pendingClose;
  }

  function openPanel() {
    panel.hidden = false;
    root.classList.add('open');
    root.classList.remove('has-unread');
    supportSetBrowserTabAlert(0, 'client-support');
    setClientTab(activeClientTab || 'interno');
  }

  function closePanel() {
    panel.hidden = true;
    root.classList.remove('open');
    root.dispatchEvent(new CustomEvent('farmflow:internal-chat-close'));
    if (messagePollTimer) clearInterval(messagePollTimer);
    messagePollTimer = null;
  }

  function loadBoot() {
    supportFetch(endpoint, 'client_boot').then(data => {
      if (!data.ok) return;
      updateClientConversationState(data);
      const messages = data.messages || [];
      lastAdminMessageId = Math.max(lastAdminMessageId, ...messages.filter(m => m.autor_tipo !== 'cliente').map(m => Number(m.id || 0)), 0);
      supportRenderMessages(messagesBox, data.messages || [], 'client');
      loadClientSummary(false);
    });
  }

  function loadMessages(playAlert = true) {
    if (!conversaId) return;
    supportFetch(endpoint, 'client_messages&conversa_id=' + encodeURIComponent(conversaId)).then(data => {
      if (!data.ok) return;
      updateClientConversationState(data);
      const messages = data.messages || [];
      const previousAdminId = lastAdminMessageId;
      const latestAdminId = Math.max(...messages.filter(m => m.autor_tipo !== 'cliente').map(m => Number(m.id || 0)), 0);
      if (playAlert !== false && latestAdminId > previousAdminId) {
        supportAlertNewMessage({
          title: 'Nova mensagem do suporte - FarmFort',
          body: 'O suporte respondeu seu atendimento.',
          count: 1,
          source: 'client-support',
          tag: 'farmflow-suporte-cliente'
        });
      }
      lastAdminMessageId = Math.max(lastAdminMessageId, latestAdminId);
      root.classList.remove('has-unread');
      supportSetBrowserTabAlert(0, 'client-support');
      if (!panel.hidden) supportRenderMessages(messagesBox, messages, 'client');
    });
  }

  function loadClientSummary(playAlert) {
    supportFetch(endpoint, 'client_summary').then(data => {
      if (!data.ok) return;
      updateClientConversationState(data);
      const unread = Number(data.unread || 0);
      const latestAdminId = Number(data.last_id || 0);
      const hasNewAdminMessage = !firstClientSummary && unread > 0 && latestAdminId > lastAdminMessageId;

      if (unread > 0) {
        root.classList.add('has-unread');
        supportSetBrowserTabAlert(unread, 'client-support');
      } else if (panel.hidden) {
        root.classList.remove('has-unread');
        supportSetBrowserTabAlert(0, 'client-support');
      }

      if (hasNewAdminMessage) {
        if (playAlert !== false) {
          supportAlertNewMessage({
            title: 'Nova mensagem do suporte - FarmFort',
            body: 'O suporte respondeu seu atendimento.',
            count: unread,
            source: 'client-support',
            tag: 'farmflow-suporte-cliente'
          });
        }
        if (panel.hidden) openPanel();
        setClientTab('suporte');
        loadMessages(false);
      }

      lastAdminMessageId = Math.max(lastAdminMessageId, latestAdminId);
      firstClientSummary = false;
    });
  }

  toggle?.addEventListener('click', () => {
    supportPrimeAudio();
    panel.hidden ? openPanel() : closePanel();
  });
  close?.addEventListener('click', closePanel);
  tabButtons.forEach(button => {
    button.addEventListener('click', () => setClientTab(button.dataset.supportClientTab || 'interno'));
  });

  finishButton?.addEventListener('click', () => {
    if (!conversaId) return;
    if (!window.confirm('Finalizar este atendimento? Você poderá abrir uma nova dúvida depois.')) return;
    const body = new FormData();
    body.append('conversa_id', conversaId);
    supportFetch(endpoint, 'client_close', { method: 'POST', body }).then(data => {
      if (!data.ok) {
        flashMsg('erro', data.erro || 'Não foi possível finalizar o chat.');
        return;
      }
      updateClientConversationState(data);
      supportRenderMessages(messagesBox, data.messages || [], 'client');
      flashMsg('ok', 'Atendimento finalizado.');
    });
  });

  keepButton?.addEventListener('click', () => {
    if (!conversaId) return;
    const body = new FormData();
    body.append('conversa_id', conversaId);
    supportFetch(endpoint, 'client_keep_open', { method: 'POST', body }).then(data => {
      if (!data.ok) {
        flashMsg('erro', data.erro || 'Não foi possível manter o atendimento aberto.');
        return;
      }
      updateClientConversationState(data);
      supportRenderMessages(messagesBox, data.messages || [], 'client');
      flashMsg('ok', 'Atendimento mantido aberto.');
    });
  });

  supportBindSoundButtons(root);

  textarea?.addEventListener('keydown', event => {
    if (event.key === 'Enter' && !event.shiftKey) {
      event.preventDefault();
      form?.requestSubmit();
    }
  });

  form?.addEventListener('submit', event => {
    event.preventDefault();
    const msg = (textarea?.value || '').trim();
    const files = Array.from(fileInput?.files || []);
    if (!msg && !files.length) return;
    if (supportRejectBlockedLanguage(msg)) return;
    if (!supportValidateAttachmentFiles(files)) return;
    const body = new FormData();
    body.append('mensagem', msg);
    files.forEach(file => body.append('anexos[]', file));
    supportFetch(endpoint, 'client_send', { method: 'POST', body }).then(data => {
      if (!data.ok) {
        flashMsg('erro', data.erro || 'Não foi possível enviar a dúvida.');
        return;
      }
      updateClientConversationState(data);
      textarea.value = '';
      supportClearAttachmentFiles(form, fileInput);
      const messages = data.messages || [];
      lastAdminMessageId = Math.max(lastAdminMessageId, ...messages.filter(m => m.autor_tipo !== 'cliente').map(m => Number(m.id || 0)), 0);
      supportRenderMessages(messagesBox, data.messages || [], 'client');
    });
  });

  loadClientSummary(false);
  if (!summaryPollTimer) summaryPollTimer = setInterval(() => loadClientSummary(true), 7000);
}

function initSupportAdmin(root) {
  const endpoint = root.dataset.supportEndpoint;
  const supportRole = root.dataset.supportRole || '';
  const floating = root.hasAttribute('data-support-floating');
  const toggle = root.querySelector('[data-support-admin-toggle]');
  const close = root.querySelector('[data-support-admin-close]');
  const panel = root.querySelector('[data-support-admin-panel]');
  const threadsBox = root.querySelector('[data-support-admin-threads]');
  const messagesBox = root.querySelector('[data-support-admin-messages]');
  const propertyBox = root.querySelector('[data-support-admin-property]');
  const form = root.querySelector('[data-support-admin-form]');
  const textarea = form ? form.querySelector('textarea[name="mensagem"]') : null;
  const fileInput = form ? form.querySelector('input[type="file"][name="anexos[]"]') : null;
  const submitButton = form ? form.querySelector('button[type="submit"]') : null;
  const assumeButton = root.querySelector('[data-support-admin-assume]');
  const requestCloseButton = root.querySelector('[data-support-admin-request-close]');
  const forwardButtons = root.querySelectorAll('[data-support-admin-forward]');
  const routingBox = root.querySelector('[data-support-admin-routing]');
  const assigneeSelect = root.querySelector('[data-support-admin-assignee]');
  const assignButton = root.querySelector('[data-support-admin-assign]');
  const badges = root.querySelectorAll('[data-support-admin-badge]');
  let selectedId = null;
  let selectedStatus = null;
  let selectedAssigneeId = null;
  let selectedAssigneeName = null;
  let selectedAssignedToMe = false;
  let selectedLevel = null;
  let selectedPropertyName = null;
  let lastClientMessageId = 0;
  let lastWaitingAlertAt = 0;
  let firstSummary = true;
  let onlineAssignees = [];

  supportBindAttachmentPreview(form, fileInput);
  supportBindSoundButtons(root);

  function adminCanForwardTo(destiny) {
    if (supportRole === 'colaborador_sistema') return destiny === 'gerencia' || destiny === 'admin';
    if (supportRole === 'gerencia_sistema') return destiny === 'admin';
    return false;
  }

  function adminCanAssignDown() {
    return supportRole === 'administrador_sistema' || supportRole === 'gerencia_sistema';
  }

  function renderAssigneeOptions() {
    const canAssign = adminCanAssignDown();
    if (routingBox) routingBox.hidden = !canAssign;
    if (!assigneeSelect) return;

    const selectedValue = assigneeSelect.value;
    if (!canAssign) {
      assigneeSelect.innerHTML = '';
      return;
    }

    const options = onlineAssignees.map(user => {
      const label = `${user.nome || 'Atendente'} - ${user.nivel_label || 'suporte'}`;
      return `<option value="${supportEscape(user.id)}">${supportEscape(label)}</option>`;
    });
    assigneeSelect.innerHTML = [
      `<option value="">${onlineAssignees.length ? 'Atendente online...' : 'Nenhum atendente abaixo online'}</option>`,
      ...options
    ].join('');
    if ([...assigneeSelect.options].some(option => option.value === selectedValue)) {
      assigneeSelect.value = selectedValue;
    }
  }

  function loadAssignees() {
    if (!adminCanAssignDown() || !assigneeSelect) {
      onlineAssignees = [];
      renderAssigneeOptions();
      updateAdminConversationState();
      return Promise.resolve();
    }
    return supportFetch(endpoint, 'admin_online_assignees').then(data => {
      onlineAssignees = data.ok ? (data.users || []) : [];
      renderAssigneeOptions();
      updateAdminConversationState();
    });
  }

  function syncSelectedSystemProperty(data = {}) {
    if (!data.propriedade_id) return;
    document.querySelectorAll('select[name="prop"]').forEach(select => {
      const hasOption = [...select.options].some(option => Number(option.value) === Number(data.propriedade_id));
      if (hasOption) select.value = String(data.propriedade_id);
    });
  }

  function updateAdminConversationState(data = {}) {
    if (Object.prototype.hasOwnProperty.call(data, 'status')) {
      selectedStatus = data.status || null;
    }
    if (Object.prototype.hasOwnProperty.call(data, 'atendente_usuario_id')) {
      selectedAssigneeId = data.atendente_usuario_id ? Number(data.atendente_usuario_id) : null;
    }
    if (Object.prototype.hasOwnProperty.call(data, 'atendente_nome')) {
      selectedAssigneeName = data.atendente_nome || null;
    }
    if (Object.prototype.hasOwnProperty.call(data, 'assumido_por_mim')) {
      selectedAssignedToMe = !!data.assumido_por_mim;
    }
    if (Object.prototype.hasOwnProperty.call(data, 'nivel_atendimento')) {
      selectedLevel = data.nivel_atendimento || null;
    }
    if (Object.prototype.hasOwnProperty.call(data, 'propriedade_nome')) {
      selectedPropertyName = data.propriedade_nome || null;
    }
    const closed = selectedStatus === 'encerrada';
    const canWork = !!selectedId && selectedAssignedToMe && !closed;
    if (propertyBox) {
      propertyBox.hidden = !selectedId;
      const title = propertyBox.querySelector('strong');
      if (title) title.textContent = selectedPropertyName || 'Sem propriedade vinculada';
    }
    if (assumeButton) {
      assumeButton.hidden = false;
      assumeButton.disabled = !selectedId || closed || selectedAssignedToMe || !!selectedAssigneeId;
      if (selectedAssignedToMe) {
        assumeButton.innerHTML = '<i class="bi bi-person-check-fill me-1"></i>Atendimento assumido por você';
      } else if (selectedAssigneeId) {
        assumeButton.innerHTML = `<i class="bi bi-lock-fill me-1"></i>Assumido por ${supportEscape(selectedAssigneeName || 'outro usuário')}`;
      } else {
        assumeButton.innerHTML = '<i class="bi bi-person-check me-1"></i>Assumir atendimento';
      }
    }
    if (requestCloseButton) {
      const pendingClose = selectedStatus === 'aguardando_encerramento';
      requestCloseButton.disabled = !canWork || pendingClose;
      requestCloseButton.innerHTML = pendingClose
        ? '<i class="bi bi-hourglass-split me-1"></i>Aguardando cliente'
        : '<i class="bi bi-check2-circle me-1"></i>Perguntar se pode finalizar';
    }
    forwardButtons.forEach(button => {
      const destiny = button.dataset.supportAdminForward || '';
      const allowed = adminCanForwardTo(destiny);
      button.hidden = !allowed;
      button.disabled = !allowed || !canWork;
    });
    if (routingBox) routingBox.hidden = !adminCanAssignDown();
    if (assigneeSelect) {
      assigneeSelect.disabled = !adminCanAssignDown() || !selectedId || closed || !onlineAssignees.length;
    }
    if (assignButton) {
      assignButton.disabled = !adminCanAssignDown() || !selectedId || closed || !onlineAssignees.length || !assigneeSelect?.value;
    }
    if (textarea) {
      textarea.disabled = !canWork;
      textarea.placeholder = canWork ? 'Responder dúvida do cliente...' : 'Assuma o atendimento para responder...';
    }
    if (fileInput) fileInput.disabled = !canWork;
    if (submitButton) submitButton.disabled = !canWork;
  }

  function setBadge(value) {
    supportSetBrowserTabAlert(value, 'admin-support');
    badges.forEach(badge => {
      badge.textContent = String(value || 0);
      if (badge.tagName === 'B') badge.hidden = !value;
    });
  }

  function openPanel() {
    if (!panel) return;
    panel.hidden = false;
    root.classList.add('open');
    loadAssignees();
  }

  function closePanel() {
    if (!panel) return;
    panel.hidden = true;
    root.classList.remove('open');
  }

  function renderThreads(threads) {
    if (!threadsBox) return;
    if (!threads || !threads.length) {
      threadsBox.innerHTML = '<div class="ff-support-empty">Nenhuma dúvida aberta.</div>';
      return;
    }
    threadsBox.innerHTML = threads.map(thread => {
      const active = Number(thread.id) === Number(selectedId);
      const unread = Number(thread.nao_lidas || 0);
      const assumedByMe = Number(thread.assumido_por_mim || 0) === 1;
      const closed = thread.status === 'encerrada';
      const pendingClose = thread.status === 'aguardando_encerramento';
      const statusLabel = closed ? 'Atendimento finalizado' : (pendingClose ? 'Aguardando encerramento' : '');
      const levelLabels = {
        colaborador: 'Triagem colaborador',
        gerencia: 'Encaminhado para gerência',
        admin: 'Encaminhado para admin'
      };
      const levelLabel = closed ? '' : (levelLabels[thread.nivel_atendimento] || '');
      const assigneeLabel = closed
        ? ''
        : (thread.atendente_usuario_id
        ? (assumedByMe ? 'Assumido por você' : `Assumido por ${thread.atendente_nome || 'outro usuário'}`)
        : 'Aguardando atendente');
      return `<button type="button" class="ff-support-thread ${active ? 'active' : ''} ${closed ? 'is-closed' : ''}" data-id="${thread.id}" data-status="${supportEscape(thread.status || '')}" data-level="${supportEscape(thread.nivel_atendimento || '')}" data-property="${supportEscape(thread.propriedade_nome || '')}">
        <strong>${supportEscape(thread.usuario_nome || 'Cliente')}</strong>
        <span class="ff-support-thread-property"><i class="bi bi-buildings me-1"></i>${supportEscape(thread.propriedade_nome || 'Sem propriedade')}</span>
        <small>${supportEscape(thread.ultima_mensagem || thread.assunto || '')}</small>
        ${levelLabel ? `<em>${supportEscape(levelLabel)}</em>` : ''}
        ${assigneeLabel ? `<em>${supportEscape(assigneeLabel)}</em>` : ''}
        ${statusLabel ? `<em>${supportEscape(statusLabel)}</em>` : ''}
        ${unread ? `<b>${unread}</b>` : ''}
      </button>`;
    }).join('');
    threadsBox.querySelectorAll('[data-id]').forEach(button => {
      button.addEventListener('click', () => {
        selectedId = Number(button.dataset.id);
        selectedStatus = button.dataset.status || null;
        selectedLevel = button.dataset.level || null;
        selectedPropertyName = button.dataset.property || null;
        updateAdminConversationState({
          status: selectedStatus,
          nivel_atendimento: selectedLevel,
          propriedade_nome: selectedPropertyName,
          atendente_usuario_id: null,
          atendente_nome: null,
          assumido_por_mim: false
        });
        loadMessages();
        loadThreads(false);
      });
    });
  }

  function loadThreads(autoSelect) {
    return supportFetch(endpoint, 'admin_threads').then(data => {
      if (!data.ok) return;
      const threads = data.threads || [];
      if (selectedId && !threads.some(t => Number(t.id) === Number(selectedId))) {
        selectedId = null;
        selectedStatus = null;
        selectedAssigneeId = null;
        selectedAssigneeName = null;
        selectedAssignedToMe = false;
        selectedLevel = null;
        selectedPropertyName = null;
        updateAdminConversationState({
          status: null,
          nivel_atendimento: null,
          propriedade_nome: null,
          atendente_usuario_id: null,
          atendente_nome: null,
          assumido_por_mim: false
        });
        if (messagesBox) messagesBox.innerHTML = '<div class="ff-support-empty">Atendimento assumido por outro usuário ou encerrado.</div>';
      }
      if (autoSelect && !selectedId && threads.length) {
        const unread = threads.find(t => Number(t.nao_lidas || 0) > 0);
        selectedId = Number((unread || threads[0]).id);
        loadMessages();
      }
      renderThreads(threads);
    });
  }

  function loadMessages(updateSummary = true) {
    if (!selectedId) return;
    supportFetch(endpoint, 'admin_messages&conversa_id=' + encodeURIComponent(selectedId)).then(data => {
      if (data.ok) {
        updateAdminConversationState(data);
        supportRenderMessages(messagesBox, data.messages || [], 'admin');
        loadThreads(false);
        if (updateSummary) pollSummary();
      } else if (data.erro) {
        flashMsg('erro', data.erro);
        selectedId = null;
        selectedLevel = null;
        selectedPropertyName = null;
        updateAdminConversationState({
          status: null,
          nivel_atendimento: null,
          propriedade_nome: null,
          atendente_usuario_id: null,
          atendente_nome: null,
          assumido_por_mim: false
        });
        loadThreads(false);
      }
    });
  }

  function pollSummary() {
    return supportFetch(endpoint, 'admin_summary').then(data => {
      if (!data.ok) return;
      const unread = Number(data.unread || 0);
      const pending = Number(data.pending || 0);
      const now = Date.now();
      setBadge(unread);
      const lastId = Number(data.last_id || 0);
      const hasNew = !firstSummary && lastId > lastClientMessageId && unread > 0;
      if (hasNew) {
        supportAlertNewMessage({
          title: 'Nova mensagem de suporte - FarmFort',
          body: 'Um cliente enviou uma nova mensagem de suporte.',
          count: unread,
          source: 'admin-support',
          tag: 'farmflow-suporte-admin'
        });
        lastWaitingAlertAt = now;
        flashMsg('ok', 'Nova mensagem de suporte recebida.');
        if (floating) openPanel();
        loadThreads(true);
      } else if (!firstSummary && pending > 0) {
        if (!lastWaitingAlertAt) lastWaitingAlertAt = now;
        if (now - lastWaitingAlertAt >= FARMFLOW_SUPPORT_REPEAT_ALERT_MS) {
          supportPlayTone();
          lastWaitingAlertAt = now;
        }
      } else if (pending <= 0) {
        lastWaitingAlertAt = 0;
      }
      if (firstSummary && pending > 0 && !lastWaitingAlertAt) {
        lastWaitingAlertAt = now;
      }
      lastClientMessageId = Math.max(lastClientMessageId, lastId);
      firstSummary = false;
    });
  }

  toggle?.addEventListener('click', () => {
    supportPrimeAudio();
    if (panel?.hidden) {
      openPanel();
      loadThreads(true);
    } else {
      closePanel();
    }
  });
  close?.addEventListener('click', closePanel);

  assumeButton?.addEventListener('click', () => {
    if (!selectedId) return;
    const body = new FormData();
    body.append('conversa_id', selectedId);
    supportFetch(endpoint, 'admin_assume', { method: 'POST', body }).then(data => {
      if (!data.ok) {
        flashMsg('erro', data.erro || 'Não foi possível assumir o atendimento.');
        loadThreads(false);
        pollSummary();
        return;
      }
      updateAdminConversationState(data);
      syncSelectedSystemProperty(data);
      supportRenderMessages(messagesBox, data.messages || [], 'admin');
      loadThreads(false);
      pollSummary();
      flashMsg('ok', 'Atendimento assumido por você.');
    });
  });

  requestCloseButton?.addEventListener('click', () => {
    if (!selectedId) return;
    const body = new FormData();
    body.append('conversa_id', selectedId);
    supportFetch(endpoint, 'admin_request_close', { method: 'POST', body }).then(data => {
      if (!data.ok) {
        flashMsg('erro', data.erro || 'Não foi possível solicitar encerramento.');
        return;
      }
      updateAdminConversationState(data);
      supportRenderMessages(messagesBox, data.messages || [], 'admin');
      loadThreads(false);
      flashMsg('ok', 'Pedido de encerramento enviado ao cliente.');
    });
  });

  assigneeSelect?.addEventListener('change', () => updateAdminConversationState());

  assignButton?.addEventListener('click', () => {
    if (!selectedId || !assigneeSelect?.value) return;
    const body = new FormData();
    body.append('conversa_id', selectedId);
    body.append('usuario_id', assigneeSelect.value);
    supportFetch(endpoint, 'admin_assign', { method: 'POST', body }).then(data => {
      if (!data.ok) {
        flashMsg('erro', data.erro || 'Não foi possível direcionar o atendimento.');
        loadAssignees();
        loadThreads(false);
        return;
      }
      updateAdminConversationState(data);
      supportRenderMessages(messagesBox, data.messages || [], 'admin');
      loadAssignees();
      loadThreads(false);
      pollSummary();
      flashMsg('ok', 'Atendimento direcionado.');
    });
  });

  forwardButtons.forEach(button => {
    button.addEventListener('click', () => {
      if (!selectedId) return;
      const destiny = button.dataset.supportAdminForward || '';
      if (!adminCanForwardTo(destiny)) return;
      const body = new FormData();
      body.append('conversa_id', selectedId);
      body.append('destino', destiny);
      supportFetch(endpoint, 'admin_forward', { method: 'POST', body }).then(data => {
        if (!data.ok) {
          flashMsg('erro', data.erro || 'Não foi possível encaminhar o atendimento.');
          loadThreads(false);
          pollSummary();
          return;
        }
        selectedId = null;
        selectedStatus = null;
        selectedAssigneeId = null;
        selectedAssigneeName = null;
        selectedAssignedToMe = false;
        selectedLevel = null;
        selectedPropertyName = null;
        updateAdminConversationState({
          status: null,
          nivel_atendimento: null,
          propriedade_nome: null,
          atendente_usuario_id: null,
          atendente_nome: null,
          assumido_por_mim: false
        });
        if (messagesBox) messagesBox.innerHTML = '<div class="ff-support-empty">Atendimento encaminhado para outra fila.</div>';
        loadThreads(true);
        pollSummary();
        flashMsg('ok', 'Atendimento encaminhado.');
      });
    });
  });

  form?.addEventListener('submit', event => {
    event.preventDefault();
    if (!selectedId) return;
    const msg = (textarea?.value || '').trim();
    const files = Array.from(fileInput?.files || []);
    if (!msg && !files.length) return;
    if (supportRejectBlockedLanguage(msg)) return;
    if (!supportValidateAttachmentFiles(files)) return;
    const body = new FormData();
    body.append('conversa_id', selectedId);
    body.append('mensagem', msg);
    files.forEach(file => body.append('anexos[]', file));
    supportFetch(endpoint, 'admin_send', { method: 'POST', body }).then(data => {
      if (!data.ok) {
        flashMsg('erro', data.erro || 'Não foi possível responder.');
        return;
      }
      textarea.value = '';
      supportClearAttachmentFiles(form, fileInput);
      updateAdminConversationState(data);
      supportRenderMessages(messagesBox, data.messages || [], 'admin');
      loadThreads(false);
    });
  });

  textarea?.addEventListener('keydown', event => {
    if (event.key === 'Enter' && !event.shiftKey) {
      event.preventDefault();
      form?.requestSubmit();
    }
  });

  if (!floating) {
    loadAssignees();
    loadThreads(true);
  }
  updateAdminConversationState();
  pollSummary();
  setInterval(() => {
    pollSummary();
    loadAssignees();
    if (selectedId) loadMessages(false);
  }, 7000);
}

function initFarmFortSupportChat() {
  if (localStorage.getItem(FARMFLOW_SUPPORT_SOUND_KEY) === null) {
    localStorage.setItem(FARMFLOW_SUPPORT_SOUND_KEY, '1');
  }
  document.addEventListener('pointerdown', supportPrimeAudio, { once: true, passive: true });
  document.addEventListener('keydown', supportPrimeAudio, { once: true });
  supportBindSoundButtons(document);
  document.querySelectorAll('[data-support-client]').forEach(initSupportClient);
  document.querySelectorAll('[data-support-admin]').forEach(initSupportAdmin);
  initFarmFortSupportAutoDock();
}

function initFarmFortSupportAutoDock() {
  const widgets = Array.from(document.querySelectorAll('.ff-support-widget'));
  if (!widgets.length) return;

  const meaningfulSelector = [
    'table',
    'thead',
    'tbody',
    'tfoot',
    'tr',
    'td',
    'th',
    'canvas',
    'input',
    'select',
    'textarea',
    'button',
    'a',
    'form',
    '.card',
    '.ff-card',
    '.ff-plan-table',
    '.ff-or-side',
    '.ff-or-chart-card',
    '.table-responsive',
    '.dataTables_wrapper',
    '[data-obstruction-sensitive]'
  ].join(',');

  function hasImportantContentBehind(widget, rect) {
    const points = [
      [rect.left + rect.width * .25, rect.top + rect.height * .5],
      [rect.left + rect.width * .5, rect.top + rect.height * .5],
      [rect.left + rect.width * .75, rect.top + rect.height * .5],
      [rect.left + rect.width * .5, rect.top + rect.height * .25],
      [rect.left + rect.width * .5, rect.top + rect.height * .75]
    ];

    return points.some(([x, y]) => {
      if (x < 0 || y < 0 || x > window.innerWidth || y > window.innerHeight) return false;
      return document.elementsFromPoint(x, y).some(element => {
        if (!element || element === document.documentElement || element === document.body) return false;
        if (widget.contains(element)) return false;
        const target = element.closest(meaningfulSelector);
        return !!target && !widget.contains(target);
      });
    });
  }

  function updateWidget(widget) {
    if (window.innerWidth < 768 || widget.classList.contains('open')) {
      widget.classList.remove('is-dodging-content');
      return;
    }

    const wasDodging = widget.classList.contains('is-dodging-content');
    const previousTransition = widget.style.transition;
    widget.style.transition = 'none';
    widget.classList.remove('is-dodging-content');
    const homeRect = widget.getBoundingClientRect();
    widget.classList.toggle('is-dodging-content', wasDodging);
    widget.style.transition = previousTransition;

    widget.classList.toggle('is-dodging-content', hasImportantContentBehind(widget, homeRect));
  }

  let frame = null;
  function scheduleUpdate() {
    if (frame) return;
    frame = window.requestAnimationFrame(() => {
      frame = null;
      widgets.forEach(updateWidget);
    });
  }

  scheduleUpdate();
  window.addEventListener('scroll', scheduleUpdate, { passive: true });
  window.addEventListener('resize', scheduleUpdate);
  document.addEventListener('shown.bs.modal', scheduleUpdate);
  document.addEventListener('hidden.bs.modal', scheduleUpdate);
  widgets.forEach(widget => {
    widget.addEventListener('click', () => setTimeout(scheduleUpdate, 60));
  });
}

function initFarmFortChartMaximizer() {
  if (typeof Chart === 'undefined') return;

  const canvases = Array.from(document.querySelectorAll('canvas'));
  const chartCanvases = canvases.filter(canvas => {
    if (!canvas.id || canvas.dataset.ffChartMaxReady === '1') return false;
    if (/ampliado|expanded|focus/i.test(canvas.id)) return false;
    if (canvas.closest('.modal, .ff-chart-fullscreen-overlay')) return false;
    const host = canvas.closest('.card, .ff-cat-chart-card, .ff-or-chart-card, .ff-bi-card, section, article, .ff-plan-card') || canvas.parentElement;
    if (host?.querySelector('.ff-fluxo-expand-btn, [data-ff-chart-native-expand]')) return false;
    return !!Chart.getChart(canvas);
  });
  if (!chartCanvases.length) return;

  let overlay = document.querySelector('.ff-chart-fullscreen-overlay');
  if (!overlay) {
    overlay = document.createElement('div');
    overlay.className = 'ff-chart-fullscreen-overlay';
    overlay.hidden = true;
    overlay.innerHTML = `
      <div class="ff-chart-fullscreen-shell" role="dialog" aria-modal="true" aria-label="Gráfico ampliado">
        <div class="ff-chart-fullscreen-head">
          <div>
            <span class="ff-chart-fullscreen-kicker">Visualização ampliada</span>
            <h3 data-ff-chart-fullscreen-title>Gráfico</h3>
          </div>
          <button type="button" class="btn btn-outline-secondary ff-chart-fullscreen-close" data-ff-chart-fullscreen-close>
            <i class="bi bi-arrow-left"></i>
            Voltar
          </button>
        </div>
        <div class="ff-chart-fullscreen-body">
          <div class="ff-chart-fullscreen-legend" data-ff-chart-fullscreen-legend hidden></div>
          <div class="ff-chart-fullscreen-canvas-wrap">
            <canvas data-ff-chart-fullscreen-canvas></canvas>
          </div>
        </div>
      </div>
    `;
    document.body.appendChild(overlay);
  }

  const fullscreenCanvas = overlay.querySelector('[data-ff-chart-fullscreen-canvas]');
  const fullscreenTitle = overlay.querySelector('[data-ff-chart-fullscreen-title]');
  const fullscreenBody = overlay.querySelector('.ff-chart-fullscreen-body');
  const fullscreenLegend = overlay.querySelector('[data-ff-chart-fullscreen-legend]');
  const closeButton = overlay.querySelector('[data-ff-chart-fullscreen-close]');
  let fullscreenChart = null;
  let fullscreenSelectedIndex = null;

  function cloneForChart(value, seen = new WeakMap()) {
    if (value === null || typeof value !== 'object') return value;
    if (value instanceof HTMLElement || value instanceof CanvasRenderingContext2D) return undefined;
    if (seen.has(value)) return seen.get(value);
    if (Array.isArray(value)) {
      const out = [];
      seen.set(value, out);
      value.forEach(item => out.push(cloneForChart(item, seen)));
      return out;
    }
    const out = {};
    seen.set(value, out);
    Object.keys(value).forEach(key => {
      if (['chart', 'ctx', 'canvas', '$context'].includes(key)) return;
      const cloned = cloneForChart(value[key], seen);
      if (cloned !== undefined) out[key] = cloned;
    });
    return out;
  }

  function chartTitleFromCanvas(canvas) {
    const card = canvas.closest('.card, .ff-cat-chart-card, .ff-or-chart-card, .ff-bi-card, .kpi-card, section, article, .ff-plan-card');
    const heading = card?.querySelector('h1,h2,h3,h4,h5,.card-header,legend');
    return (heading?.textContent || canvas.getAttribute('aria-label') || 'Gráfico').trim().replace(/\s+/g, ' ');
  }

  function externalLegendFromCanvas(canvas) {
    const chartArea = canvas.closest('.ff-cat-chart-area');
    const legend = chartArea?.querySelector('.ff-cat-legend');
    return legend || null;
  }

  function setFullscreenLegendState(index) {
    fullscreenLegend.querySelectorAll('[data-ff-fullscreen-legend-index]').forEach(item => {
      item.classList.toggle('is-active', Number(item.dataset.ffFullscreenLegendIndex) === index);
    });
  }

  function clearFullscreenSelection() {
    fullscreenSelectedIndex = null;
    setFullscreenLegendState(null);
    if (fullscreenChart?.data?.datasets?.[0]) {
      const count = fullscreenChart.data.datasets[0].data?.length || 0;
      fullscreenChart.data.datasets[0].offset = Array.from({ length: count }, () => 0);
      fullscreenChart.data.datasets[0].borderWidth = Array.from({ length: count }, () => 3);
      fullscreenChart.setActiveElements([]);
      fullscreenChart.update();
    }
  }

  function selectFullscreenCategory(index) {
    if (fullscreenSelectedIndex === index) {
      clearFullscreenSelection();
      return;
    }
    fullscreenSelectedIndex = index;
    setFullscreenLegendState(index);
    if (fullscreenChart?.data?.datasets?.[0]) {
      const count = fullscreenChart.data.datasets[0].data?.length || 0;
      fullscreenChart.data.datasets[0].offset = Array.from({ length: count }, (_, itemIndex) => itemIndex === index ? 16 : 0);
      fullscreenChart.data.datasets[0].borderWidth = Array.from({ length: count }, (_, itemIndex) => itemIndex === index ? 5 : 3);
      fullscreenChart.setActiveElements([{ datasetIndex: 0, index }]);
      fullscreenChart.update();
    }
  }

  function renderFullscreenLegend(canvas) {
    const sourceLegend = externalLegendFromCanvas(canvas);
    fullscreenLegend.innerHTML = '';
    fullscreenLegend.hidden = true;
    fullscreenBody.classList.remove('has-legend');
    fullscreenSelectedIndex = null;
    if (!sourceLegend) return false;

    const title = document.createElement('span');
    title.className = 'ff-chart-fullscreen-legend-title';
    title.textContent = 'Legenda';
    fullscreenLegend.appendChild(title);

    Array.from(sourceLegend.children).forEach(item => {
      const itemIndex = Number(item.dataset.legendIndex);
      const cloned = item.cloneNode(true);
      cloned.removeAttribute('data-legend-index');
      if (Number.isFinite(itemIndex)) {
        cloned.dataset.ffFullscreenLegendIndex = String(itemIndex);
      }
      cloned.classList.remove('is-active', 'is-locked');
      cloned.querySelectorAll('[data-legend-index]').forEach(child => child.removeAttribute('data-legend-index'));
      if (cloned.tagName === 'BUTTON') {
        cloned.type = 'button';
        cloned.disabled = false;
        cloned.addEventListener('click', () => {
          if (Number.isFinite(itemIndex)) selectFullscreenCategory(itemIndex);
        });
      }
      fullscreenLegend.appendChild(cloned);
    });
    fullscreenLegend.hidden = false;
    fullscreenBody.classList.add('has-legend');
    return true;
  }

  function destroyFullscreenChart() {
    if (fullscreenChart) {
      fullscreenChart.destroy();
      fullscreenChart = null;
    }
  }

  function closeFullscreen() {
    destroyFullscreenChart();
    fullscreenLegend.innerHTML = '';
    fullscreenLegend.hidden = true;
    fullscreenBody.classList.remove('has-legend');
    fullscreenSelectedIndex = null;
    overlay.hidden = true;
    document.body.classList.remove('ff-chart-fullscreen-open');
  }

  function openFullscreen(canvas) {
    const sourceChart = Chart.getChart(canvas);
    if (!sourceChart) return;
    destroyFullscreenChart();
    fullscreenTitle.textContent = chartTitleFromCanvas(canvas);
    const hasExternalLegend = renderFullscreenLegend(canvas);
    overlay.hidden = false;
    document.body.classList.add('ff-chart-fullscreen-open');

    const config = {
      type: sourceChart.config.type,
      data: cloneForChart(sourceChart.config.data),
      options: cloneForChart(sourceChart.config.options || {}),
      plugins: cloneForChart(sourceChart.config.plugins || [])
    };
    config.options = config.options || {};
    config.options.responsive = true;
    config.options.maintainAspectRatio = false;
    if (config.options.plugins?.legend) {
      config.options.plugins.legend.display = config.options.plugins.legend.display !== false;
    }
    if (hasExternalLegend) {
      config.options.onHover = (event, active) => {
        fullscreenCanvas.style.cursor = active && active.length ? 'pointer' : 'default';
      };
      config.options.onClick = (event) => {
        const clicked = fullscreenChart?.getElementsAtEventForMode(event, 'nearest', { intersect: true }, true);
        if (clicked && clicked.length) {
          selectFullscreenCategory(clicked[0].index);
        } else {
          clearFullscreenSelection();
        }
      };
    }

    setTimeout(() => {
      fullscreenChart = new Chart(fullscreenCanvas, config);
      fullscreenChart.resize();
    }, 0);
  }

  function findButtonHost(canvas) {
    return canvas.closest('.card, .ff-cat-chart-card, .ff-or-chart-card, .ff-bi-card, section, article, .ff-plan-card') || canvas.parentElement;
  }

  chartCanvases.forEach(canvas => {
    const host = findButtonHost(canvas);
    if (!host) return;
    canvas.dataset.ffChartMaxReady = '1';
    host.classList.add('ff-chart-max-host');

    const header = host.querySelector('.card-header, .ff-cat-card-head, .ff-or-card-head, h1, h2, h3, h4, h5') || host;
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'ff-chart-max-btn';
    button.title = 'Ampliar gráfico';
    button.setAttribute('aria-label', 'Ampliar gráfico');
    button.innerHTML = '<i class="bi bi-arrows-fullscreen"></i>';
    button.addEventListener('click', event => {
      event.preventDefault();
      event.stopPropagation();
      openFullscreen(canvas);
    });
    header.classList.add('ff-chart-max-head');
    header.appendChild(button);
  });

  closeButton?.addEventListener('click', closeFullscreen);
  overlay.addEventListener('click', event => {
    if (event.target === overlay) closeFullscreen();
  });
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && !overlay.hidden) closeFullscreen();
  });
}

document.addEventListener('DOMContentLoaded', function () {
  initFarmFortTheme();
  initModuleRailScroll();
  initFarmFortSupportChat();

  initDataTable('.datatable');
  initFarmFortChartMaximizer();

  document.querySelectorAll('.moeda').forEach(el => {
    el.addEventListener('input', () => mascaraMoeda(el));
  });

  if (window.bootstrap) {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
      new bootstrap.Tooltip(el);
    });

    setTimeout(() => {
      document.querySelectorAll('.alert-auto').forEach(el => {
        const bsAlert = bootstrap.Alert.getOrCreateInstance(el);
        bsAlert.close();
      });
    }, 4000);
  }
});
