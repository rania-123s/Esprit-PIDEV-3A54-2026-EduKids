(() => {
  const root = document.getElementById('chatApp');
  if (!root) {
    return;
  }

  const normalizeId = (value) => {
    const normalized = Number(value);
    return Number.isFinite(normalized) && normalized > 0 ? normalized : null;
  };

  const userId = normalizeId(root.dataset.userId);
  const csrf = root.dataset.csrf || '';
  const mercureUrl = root.dataset.mercureUrl || '';
  const websocketUrl = root.dataset.websocketUrl || '';
  const websocketToken = root.dataset.websocketToken || '';
  const initialConversationId = Number(root.dataset.initialConversationId || 0);

  const urls = {
    chatIndex: root.dataset.chatIndexUrl || '/chat',
    chatShow: root.dataset.chatShowUrlTemplate,
    conversations: root.dataset.conversationsUrl,
    conversationSummary: root.dataset.conversationSummaryUrlTemplate,
    conversationHide: root.dataset.conversationHideUrlTemplate,
    createPrivate: root.dataset.createPrivateUrl,
    createGroup: root.dataset.createGroupUrl,
    parentSearch: root.dataset.parentSearchUrl,
    messages: root.dataset.messagesUrlTemplate,
    messageCreate: root.dataset.messageCreateUrlTemplate,
    messageAudioCreate: root.dataset.messageAudioCreateUrlTemplate,
    typing: root.dataset.typingUrlTemplate,
    messageUpdate: root.dataset.messageUpdateUrlTemplate,
    messageDelete: root.dataset.messageDeleteUrlTemplate,
    attachmentSummarize: root.dataset.attachmentSummarizeUrlTemplate,
    groupLeave: root.dataset.groupLeaveUrlTemplate,
    groupMembersList: root.dataset.groupMemberListUrlTemplate,
    groupMemberAdd: root.dataset.groupMemberAddUrlTemplate,
    groupMemberRemove: root.dataset.groupMemberRemoveUrlTemplate,
    conversationImages: root.dataset.conversationImagesUrlTemplate,
    translate: root.dataset.translateUrl || '/api/translate',
  };

  const conversationListEl = document.getElementById('chatConversationList');
  const chatMessagesEl = document.getElementById('chatMessages');
  const chatTitleEl = document.getElementById('chatTitle');
  const chatSearchEl = document.getElementById('chatSearch');
  const newConversationBtn = document.getElementById('chatNewConversation');
  const menuBtn = document.getElementById('chatConversationMenuBtn');
  const menuEl = document.getElementById('chatConversationMenu');
  const hideConversationMenuItem = document.getElementById('chatHideConversationAction');
  const manageMembersMenuItem = document.getElementById('chatManageMembersAction');
  const leaveGroupMenuItem = document.getElementById('chatLeaveGroupAction');
  const chatInputEl = document.getElementById('chatInput');
  const chatSendBtn = document.getElementById('chatSendBtn');
  const chatEmojiBtn = document.getElementById('chatEmojiBtn');
  const chatEmojiPicker = document.getElementById('chatEmojiPicker');
  const chatFileInput = document.getElementById('chatFileInput');
  const chatVoiceToggleBtn = document.getElementById('chatVoiceToggleBtn');
  const chatVoiceRecorder = document.getElementById('chatVoiceRecorder');
  const chatVoiceDuration = document.getElementById('chatVoiceDuration');
  const chatVoiceWave = document.getElementById('chatVoiceWave');
  const chatVoicePreviewAudio = document.getElementById('chatVoicePreviewAudio');
  const chatVoiceStatus = document.getElementById('chatVoiceStatus');
  const chatVoiceStartBtn = document.getElementById('chatVoiceStartBtn');
  const chatVoiceStopBtn = document.getElementById('chatVoiceStopBtn');
  const chatVoicePreviewPlayBtn = document.getElementById('chatVoicePreviewPlayBtn');
  const chatVoiceSendBtn = document.getElementById('chatVoiceSendBtn');
  const chatVoiceCancelBtn = document.getElementById('chatVoiceCancelBtn');
  const chatUploadPreview = document.getElementById('chatUploadPreview');
  const chatUploadStatus = document.getElementById('chatUploadStatus');
  const typingIndicatorsEl = document.getElementById('chatTypingIndicators');
  const editBanner = document.getElementById('chatEditBanner');
  const editCancelBtn = document.getElementById('chatEditCancel');
  const imagesModal = document.getElementById('chatImagesModal');
  const imagesCloseBtn = document.getElementById('chatImagesClose');
  const imagesGrid = document.getElementById('chatImagesGrid');
  const membersModal = document.getElementById('chatMembersModal');
  const membersCloseBtn = document.getElementById('chatMembersClose');
  const membersSearchInput = document.getElementById('chatMembersSearchInput');
  const membersSearchResultsEl = document.getElementById('chatMembersSearchResults');
  const membersListEl = document.getElementById('chatMembersList');
  const membersErrorEl = document.getElementById('chatMembersError');

  const createModal = document.getElementById('chatCreateModal');
  const createCloseBtn = document.getElementById('chatCreateClose');
  const createCancelBtn = document.getElementById('chatCreateCancel');
  const createConfirmBtn = document.getElementById('chatCreateConfirm');
  const createHintEl = document.getElementById('chatCreateHint');
  const modePrivateBtn = document.getElementById('chatModePrivate');
  const modeGroupBtn = document.getElementById('chatModeGroup');
  const parentSearchInput = document.getElementById('chatParentSearchInput');
  const parentResultsEl = document.getElementById('chatParentResults');
  const selectedParentsEl = document.getElementById('chatSelectedParents');
  const selectedSection = document.querySelector('.chat-selected-section');
  const groupNameWrap = document.getElementById('chatGroupNameWrap');
  const groupNameInput = document.getElementById('chatGroupNameInput');
  const createErrorEl = document.getElementById('chatCreateError');

  let activeConversationId = null;
  let editingMessageId = null;
  let initialApplied = false;
  let searchTimer = null;
  let membersSearchTimer = null;
  let creatingConversation = false;
  let createMode = 'private';
  let lastConversations = [];
  let parentSearchResults = [];
  let membersSearchResults = [];
  let membersLoading = false;
  let activeGroupMemberIds = new Set();
  const selectedParents = new Map();
  const remoteTypingByConversation = new Map();
  const TYPING_DEBOUNCE_MS = 350;
  const TYPING_IDLE_TIMEOUT_MS = 2600;
  const TYPING_REMOTE_TIMEOUT_MS = 3200;
  let typingDebounceTimer = null;
  let typingIdleTimer = null;
  let localTypingConversationId = null;
  let localTypingState = false;
  const VOICE_MAX_SECONDS = 120;
  const VOICE_MIME_TYPES = [
    'audio/webm;codecs=opus',
    'audio/ogg;codecs=opus',
    'audio/webm',
    'audio/ogg',
    'audio/mp4',
  ];
  let mediaRecorder = null;
  let mediaStream = null;
  let recordedAudioChunks = [];
  let recordedAudioBlob = null;
  let recordedAudioDurationSeconds = 0;
  let recordingStartedAt = 0;
  let recordingTimer = null;
  let selectedAudioMimeType = '';
  let voicePreviewUrl = null;
  let ignoreNextRecorderStop = false;
  let sendingVoiceMessage = false;
  let voiceRecorderState = 'idle';
  let activeAudioElement = null;
  let voiceStatusTimer = null;
  let recordingStoppedByLimit = false;
  let voiceLongPressTimer = null;
  let voiceLongPressTriggered = false;
  const attachmentSummaryState = new Map();
  const messageTextById = new Map();
  const translationStateByKey = new Map();
  const translationCacheByKey = new Map();
  const translationInFlightByKey = new Map();
  const TRANSLATION_MAX_TEXT_LENGTH = 5000;
  const SUPPORTED_TRANSLATION_LANGUAGES = ['en', 'fr', 'ar'];
  const SUPPORTED_TRANSLATION_LANGUAGE_SET = new Set(SUPPORTED_TRANSLATION_LANGUAGES);
  const TRANSLATE_LOADING_LABEL = 'Traduction...';
  const SUMMARY_SUPPORTED_MIME_TYPES = new Set([
    'application/pdf',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'text/plain',
  ]);
  const SUMMARY_SUPPORTED_EXTENSIONS = new Set(['pdf', 'docx', 'txt']);

  const escapeHtml = (value) => {
    const div = document.createElement('div');
    div.textContent = value || '';
    return div.innerHTML;
  };

  const formatTime = (iso) => {
    if (!iso) {
      return '';
    }

    const date = new Date(iso);
    return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
  };

  const formatFileSize = (bytes) => {
    const value = Number(bytes || 0);
    if (!Number.isFinite(value) || value <= 0) {
      return '0 B';
    }

    const units = ['B', 'KB', 'MB', 'GB'];
    let size = value;
    let unitIndex = 0;
    while (size >= 1024 && unitIndex < units.length - 1) {
      size /= 1024;
      unitIndex += 1;
    }

    const fixed = size >= 10 || unitIndex === 0 ? 0 : 1;
    return `${size.toFixed(fixed)} ${units[unitIndex]}`;
  };

  const formatDuration = (value) => {
    const seconds = Math.max(0, Math.floor(Number(value) || 0));
    const minutesPart = String(Math.floor(seconds / 60)).padStart(2, '0');
    const secondsPart = String(seconds % 60).padStart(2, '0');
    return `${minutesPart}:${secondsPart}`;
  };

  const resolveAttachmentExtension = (attachment) => {
    const name = String(attachment?.name || '').trim().toLowerCase();
    if (!name.includes('.')) {
      return '';
    }

    return name.split('.').pop() || '';
  };

  const isSummarizableAttachment = (attachment) => {
    if (!attachment || attachment.isImage || isAudioAttachment(attachment)) {
      return false;
    }

    const mimeType = String(attachment.mimeType || '').trim().toLowerCase();
    const extension = resolveAttachmentExtension(attachment);

    if (SUMMARY_SUPPORTED_MIME_TYPES.has(mimeType)) {
      return true;
    }

    return SUMMARY_SUPPORTED_EXTENSIONS.has(extension);
  };

  const isAudioAttachment = (attachment) => {
    if (!attachment) {
      return false;
    }

    if (attachment.type === 'audio') {
      return true;
    }

    const mimeType = String(attachment.mimeType || '').toLowerCase();
    return mimeType.startsWith('audio/');
  };

  const isAudioMessage = (message) => {
    if (message?.type === 'audio') {
      return true;
    }

    if (!Array.isArray(message?.attachments)) {
      return false;
    }

    return message.attachments.some((attachment) => isAudioAttachment(attachment));
  };

  const toMessageExcerpt = (value, maxLength = 55) => {
    const normalized = String(value || '').replace(/\s+/g, ' ').trim();
    if (!normalized) {
      return '';
    }

    if (normalized.length <= maxLength) {
      return normalized;
    }

    return `${normalized.slice(0, maxLength - 3).trim()}...`;
  };

  const buildMessagePreview = (message) => {
    if (!message) {
      return '';
    }

    if (message.deletedAt) {
      return 'Message supprime';
    }

    const excerpt = toMessageExcerpt(message.content || '');
    if (excerpt) {
      return excerpt;
    }

    if (isAudioMessage(message)) {
      const senderId = normalizeId(message.senderId);
      if (senderId !== null && userId !== null && senderId === userId) {
        return 'Message vocal';
      }

      const senderName = String(message.senderName || '').trim() || 'Un membre';
      return `${senderName} a envoye un message vocal`;
    }

    const hasAttachment = (
      (Array.isArray(message.attachments) && message.attachments.length > 0)
      || Boolean(message.filePath)
      || message.type === 'image'
      || message.type === 'file'
    );
    if (!hasAttachment) {
      return 'Message';
    }

    const senderId = normalizeId(message.senderId);
    if (senderId !== null && userId !== null && senderId === userId) {
      return 'Vous avez envoyé une pièce jointe';
    }

    const senderName = String(message.senderName || '').trim() || 'Un membre';
    return `${senderName} a envoyé une pièce jointe`;
  };

  const setInputEnabled = (enabled) => {
    if (chatInputEl) chatInputEl.disabled = !enabled;
    if (chatSendBtn) chatSendBtn.disabled = !enabled;
    if (chatFileInput) chatFileInput.disabled = !enabled;
    if (chatVoiceToggleBtn) chatVoiceToggleBtn.disabled = !enabled;
    if (chatVoiceStartBtn) chatVoiceStartBtn.disabled = !enabled;
    if (chatVoiceStopBtn) chatVoiceStopBtn.disabled = !enabled;
    if (chatVoicePreviewPlayBtn) chatVoicePreviewPlayBtn.disabled = !enabled;
    if (chatVoiceSendBtn) chatVoiceSendBtn.disabled = !enabled;
    if (chatVoiceCancelBtn) chatVoiceCancelBtn.disabled = !enabled;
    refreshVoiceRecorderButtons();
  };

  const resolveUrl = (template, id) => String(template || '').replace('{id}', String(id));
  const buildMessagesUrl = (conversationId) => {
    const baseUrl = resolveUrl(urls.messages, conversationId);
    if (!baseUrl) {
      return baseUrl;
    }

    return `${baseUrl}${baseUrl.includes('?') ? '&' : '?'}all=1`;
  };

  const readJsonSafe = async (response) => {
    try {
      return await response.json();
    } catch {
      return null;
    }
  };

  const apiFetch = async (url, options = {}) => {
    const headers = options.headers ? { ...options.headers } : {};
    headers['X-CSRF-TOKEN'] = csrf;

    return fetch(url, {
      ...options,
      headers,
    });
  };

  const normalizeMessageText = (value) => String(value || '').trim();

  const buildTranslationKey = (messageId, target) => `${String(messageId)}:${String(target).toLowerCase()}`;

  const getTranslationState = (messageId, target) => {
    const key = buildTranslationKey(messageId, target);
    if (!translationStateByKey.has(key)) {
      translationStateByKey.set(key, {
        visible: false,
        loading: false,
        translatedText: '',
        errorMessage: '',
      });
    }

    return translationStateByKey.get(key);
  };

  const clearMessageTranslationState = (messageId) => {
    const idKey = String(messageId);

    Array.from(translationStateByKey.keys()).forEach((key) => {
      if (key.startsWith(`${idKey}:`)) {
        translationStateByKey.delete(key);
      }
    });

    Array.from(translationCacheByKey.keys()).forEach((key) => {
      if (key.startsWith(`${idKey}:`)) {
        translationCacheByKey.delete(key);
      }
    });

    Array.from(translationInFlightByKey.keys()).forEach((key) => {
      if (key.startsWith(`${idKey}:`)) {
        translationInFlightByKey.delete(key);
      }
    });
  };

  const syncMessageTextCache = (message) => {
    const messageId = String(message?.id || '');
    if (messageId === '') {
      return;
    }

    const nextText = normalizeMessageText(message?.content);
    const previousText = messageTextById.get(messageId);
    messageTextById.set(messageId, nextText);

    if (previousText !== undefined && previousText !== nextText) {
      clearMessageTranslationState(messageId);
    }
  };

  const setTranslationState = (messageId, target, nextState) => {
    const key = buildTranslationKey(messageId, target);
    const state = {
      visible: false,
      loading: false,
      translatedText: '',
      errorMessage: '',
      ...nextState,
    };

    translationStateByKey.set(key, state);
    return state;
  };

  const setTranslateButtonState = (button, state, target) => {
    if (!(button instanceof HTMLButtonElement)) {
      return;
    }

    button.disabled = Boolean(state.loading);
    if (state.loading) {
      button.textContent = TRANSLATE_LOADING_LABEL;
      return;
    }

    const targetLabel = String(target || '').toUpperCase();
    button.textContent = state.visible ? `Masquer ${targetLabel}` : targetLabel;
  };

  const renderMessageTranslationUi = (wrapper, messageId) => {
    if (!(wrapper instanceof HTMLElement)) {
      return;
    }

    const actionsContainer = wrapper.querySelector('.js-translate-actions');
    const statusEl = wrapper.querySelector('.js-translate-status');
    const listContainer = wrapper.querySelector('.js-translation-list');
    if (!(actionsContainer instanceof HTMLElement) || !(listContainer instanceof HTMLElement)) {
      return;
    }

    const idKey = String(messageId);
    const sourceText = normalizeMessageText(messageTextById.get(idKey));
    const targets = SUPPORTED_TRANSLATION_LANGUAGES;

    actionsContainer.innerHTML = '';
    listContainer.innerHTML = '';

    if (!(statusEl instanceof HTMLElement)) {
      return;
    }

    if (sourceText === '') {
      statusEl.hidden = true;
      return;
    }

    statusEl.hidden = true;

    targets.forEach((target) => {
      const state = getTranslationState(idKey, target);
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'chat-translate-btn js-translate-message';
      button.dataset.messageId = idKey;
      button.dataset.target = target;
      setTranslateButtonState(button, state, target);
      actionsContainer.appendChild(button);

      const box = document.createElement('div');
      box.className = 'chat-translation';
      box.dataset.target = target;

      const title = document.createElement('div');
      title.className = 'chat-translation-title';

      const text = document.createElement('div');
      text.className = 'chat-translation-text';

      if (!state.visible) {
        box.hidden = true;
      } else if (state.loading) {
        box.hidden = false;
        title.textContent = `Traduction (${target.toUpperCase()})`;
        text.textContent = TRANSLATE_LOADING_LABEL;
      } else {
        box.hidden = false;
        const isError = Boolean(state.errorMessage);
        box.classList.toggle('is-error', isError);
        title.textContent = isError ? `Erreur (${target.toUpperCase()})` : `Traduction (${target.toUpperCase()})`;
        text.textContent = isError ? state.errorMessage : state.translatedText;
      }

      box.appendChild(title);
      box.appendChild(text);
      listContainer.appendChild(box);
    });
  };

  const requestMessageTranslation = async (text, target) => {
    if (!SUPPORTED_TRANSLATION_LANGUAGE_SET.has(target)) {
      throw new Error('Langue cible invalide.');
    }

    const response = await apiFetch(urls.translate, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        text,
        source: 'auto',
        target,
      }),
    });
    const payload = await readJsonSafe(response);

    if (!response.ok) {
      const errorMessage = payload && typeof payload.error === 'string'
        ? payload.error
        : 'Service de traduction indisponible.';
      throw new Error(errorMessage);
    }

    const translatedText = normalizeMessageText(payload?.translatedText);
    if (translatedText === '') {
      throw new Error('La traduction est vide.');
    }

    return translatedText;
  };

  const handleMessageTranslationToggle = async (button) => {
    if (!(button instanceof HTMLButtonElement)) {
      return;
    }

    const wrapper = button.closest('.chat-message');
    if (!(wrapper instanceof HTMLElement)) {
      return;
    }

    const messageId = String(wrapper.dataset.id || button.dataset.messageId || '').trim();
    const target = String(button.dataset.target || '').trim().toLowerCase();
    if (messageId === '' || !SUPPORTED_TRANSLATION_LANGUAGE_SET.has(target)) {
      return;
    }

    const state = getTranslationState(messageId, target);
    if (state.loading) {
      return;
    }

    if (state.visible) {
      setTranslationState(messageId, target, {
        ...state,
        visible: false,
        loading: false,
      });
      renderMessageTranslationUi(wrapper, messageId);
      return;
    }

    const sourceText = normalizeMessageText(messageTextById.get(messageId));
    if (sourceText === '') {
      setTranslationState(messageId, target, {
        visible: true,
        loading: false,
        translatedText: '',
        errorMessage: 'Aucun texte a traduire.',
      });
      renderMessageTranslationUi(wrapper, messageId);
      return;
    }

    if (sourceText.length > TRANSLATION_MAX_TEXT_LENGTH) {
      setTranslationState(messageId, target, {
        visible: true,
        loading: false,
        translatedText: '',
        errorMessage: `Le texte depasse ${TRANSLATION_MAX_TEXT_LENGTH} caracteres.`,
      });
      renderMessageTranslationUi(wrapper, messageId);
      return;
    }

    const cacheKey = buildTranslationKey(messageId, target);
    const cached = translationCacheByKey.get(cacheKey);
    if (
      cached
      && cached.sourceText === sourceText
      && cached.target === target
      && cached.translatedText
    ) {
      setTranslationState(messageId, target, {
        visible: true,
        loading: false,
        translatedText: cached.translatedText,
        errorMessage: '',
      });
      renderMessageTranslationUi(wrapper, messageId);
      return;
    }

    setTranslationState(messageId, target, {
      ...state,
      visible: true,
      loading: true,
      errorMessage: '',
    });
    renderMessageTranslationUi(wrapper, messageId);

    try {
      const inFlight = translationInFlightByKey.get(cacheKey);
      let translationPromise = null;

      if (inFlight && inFlight.sourceText === sourceText) {
        translationPromise = inFlight.promise;
      } else {
        translationPromise = requestMessageTranslation(sourceText, target);
        translationInFlightByKey.set(cacheKey, {
          sourceText,
          promise: translationPromise,
        });
      }

      const translatedText = await translationPromise;

      translationCacheByKey.set(cacheKey, {
        sourceText,
        target,
        translatedText,
      });
      setTranslationState(messageId, target, {
        visible: true,
        loading: false,
        translatedText,
        errorMessage: '',
      });
    } catch (error) {
      setTranslationState(messageId, target, {
        visible: true,
        loading: false,
        translatedText: '',
        errorMessage: error instanceof Error ? error.message : 'Erreur de traduction.',
      });
    } finally {
      const inFlight = translationInFlightByKey.get(cacheKey);
      if (inFlight && inFlight.sourceText === sourceText) {
        translationInFlightByKey.delete(cacheKey);
      }
    }

    renderMessageTranslationUi(wrapper, messageId);
  };

  const clearLocalTypingTimers = () => {
    if (typingDebounceTimer) {
      clearTimeout(typingDebounceTimer);
      typingDebounceTimer = null;
    }

    if (typingIdleTimer) {
      clearTimeout(typingIdleTimer);
      typingIdleTimer = null;
    }
  };

  const publishTypingState = (typing, conversationId = activeConversationId, options = {}) => {
    const targetConversationId = normalizeId(conversationId);
    if (targetConversationId === null || !urls.typing) {
      return;
    }

    const force = options.force === true;
    const alreadyInState = localTypingConversationId === targetConversationId && localTypingState === typing;
    if (!force && alreadyInState) {
      return;
    }

    if (typing) {
      localTypingConversationId = targetConversationId;
      localTypingState = true;
    } else if (localTypingConversationId === targetConversationId || force) {
      localTypingConversationId = null;
      localTypingState = false;
    }

    void apiFetch(resolveUrl(urls.typing, targetConversationId), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrf,
      },
      body: JSON.stringify({ typing }),
      keepalive: options.keepalive === true,
    }).catch(() => {});
  };

  const stopLocalTyping = (options = {}) => {
    clearLocalTypingTimers();

    const targetConversationId = normalizeId(
      options.conversationId ?? localTypingConversationId ?? activeConversationId
    );
    if (targetConversationId === null) {
      return;
    }

    publishTypingState(false, targetConversationId, {
      force: options.force !== false,
      keepalive: options.keepalive === true,
    });
  };

  const removeRemoteTypingUser = (conversationId, memberId) => {
    const normalizedConversationId = normalizeId(conversationId);
    const normalizedMemberId = normalizeId(memberId);
    if (normalizedConversationId === null || normalizedMemberId === null) {
      return;
    }

    const conversationTyping = remoteTypingByConversation.get(normalizedConversationId);
    if (!conversationTyping) {
      return;
    }

    const existingEntry = conversationTyping.get(normalizedMemberId);
    if (existingEntry && existingEntry.timeoutId) {
      clearTimeout(existingEntry.timeoutId);
    }

    conversationTyping.delete(normalizedMemberId);
    if (conversationTyping.size === 0) {
      remoteTypingByConversation.delete(normalizedConversationId);
    }
  };

  const renderTypingIndicators = () => {
    if (!typingIndicatorsEl) {
      return;
    }

    const targetConversationId = normalizeId(activeConversationId);
    if (targetConversationId === null) {
      typingIndicatorsEl.hidden = true;
      typingIndicatorsEl.innerHTML = '';
      return;
    }

    const conversationTyping = remoteTypingByConversation.get(targetConversationId);
    if (!conversationTyping || conversationTyping.size === 0) {
      typingIndicatorsEl.hidden = true;
      typingIndicatorsEl.innerHTML = '';
      return;
    }

    const lines = [];
    conversationTyping.forEach((entry, memberId) => {
      if (userId !== null && memberId === userId) {
        return;
      }

      const rawName = String(entry?.name || '').trim();
      const label = `${rawName || 'Un membre'} est en train d'ecrire`;
      lines.push(`
        <div class="chat-typing-line">
          <span class="chat-typing-label">${escapeHtml(label)}</span>
          <span class="chat-typing-dots" aria-hidden="true">
            <span class="chat-typing-dot"></span>
            <span class="chat-typing-dot"></span>
            <span class="chat-typing-dot"></span>
          </span>
        </div>
      `);
    });

    if (lines.length === 0) {
      typingIndicatorsEl.hidden = true;
      typingIndicatorsEl.innerHTML = '';
      return;
    }

    typingIndicatorsEl.hidden = false;
    typingIndicatorsEl.innerHTML = lines.join('');
  };

  const upsertRemoteTyping = (payload) => {
    const conversationId = normalizeId(payload?.conversationId);
    const memberId = normalizeId(payload?.userId);
    if (conversationId === null || memberId === null) {
      return;
    }

    if (userId !== null && memberId === userId) {
      return;
    }

    if (!payload.typing) {
      removeRemoteTypingUser(conversationId, memberId);
      if (conversationId === normalizeId(activeConversationId)) {
        renderTypingIndicators();
      }
      return;
    }

    let conversationTyping = remoteTypingByConversation.get(conversationId);
    if (!conversationTyping) {
      conversationTyping = new Map();
      remoteTypingByConversation.set(conversationId, conversationTyping);
    }

    const existingEntry = conversationTyping.get(memberId);
    if (existingEntry && existingEntry.timeoutId) {
      clearTimeout(existingEntry.timeoutId);
    }

    const timeoutId = setTimeout(() => {
      removeRemoteTypingUser(conversationId, memberId);
      if (conversationId === normalizeId(activeConversationId)) {
        renderTypingIndicators();
      }
    }, TYPING_REMOTE_TIMEOUT_MS);

    conversationTyping.set(memberId, {
      name: payload.userName || 'Un membre',
      timeoutId,
    });

    if (conversationId === normalizeId(activeConversationId)) {
      renderTypingIndicators();
    }
  };

  const handleLocalTypingInput = () => {
    if (!chatInputEl) {
      return;
    }

    const targetConversationId = normalizeId(activeConversationId);
    if (targetConversationId === null) {
      stopLocalTyping({ force: true });
      return;
    }

    const hasText = chatInputEl.value.trim() !== '';
    if (!hasText) {
      stopLocalTyping({ conversationId: targetConversationId, force: true });
      return;
    }

    if (localTypingConversationId !== null && localTypingConversationId !== targetConversationId) {
      stopLocalTyping({ conversationId: localTypingConversationId, force: true });
    }

    if (typingDebounceTimer) {
      clearTimeout(typingDebounceTimer);
    }
    typingDebounceTimer = setTimeout(() => {
      publishTypingState(true, targetConversationId);
    }, TYPING_DEBOUNCE_MS);

    if (typingIdleTimer) {
      clearTimeout(typingIdleTimer);
    }
    typingIdleTimer = setTimeout(() => {
      stopLocalTyping({ conversationId: targetConversationId, force: true });
    }, TYPING_IDLE_TIMEOUT_MS);
  };

  const setCreateError = (message) => {
    if (!createErrorEl) {
      return;
    }

    if (!message) {
      createErrorEl.hidden = true;
      createErrorEl.textContent = '';
      return;
    }

    createErrorEl.hidden = false;
    createErrorEl.textContent = message;
  };

  const setMembersError = (message) => {
    if (!membersErrorEl) {
      return;
    }

    if (!message) {
      membersErrorEl.hidden = true;
      membersErrorEl.textContent = '';
      return;
    }

    membersErrorEl.hidden = false;
    membersErrorEl.textContent = message;
  };

  const setUploadStatus = (message, isError = false) => {
    if (!chatUploadStatus) {
      return;
    }

    if (!message) {
      chatUploadStatus.hidden = true;
      chatUploadStatus.textContent = '';
      chatUploadStatus.classList.remove('is-error');
      return;
    }

    chatUploadStatus.hidden = false;
    chatUploadStatus.textContent = message;
    chatUploadStatus.classList.toggle('is-error', isError);
  };

  const setVoiceStatus = (message, options = {}) => {
    if (!chatVoiceStatus) {
      return;
    }

    if (voiceStatusTimer) {
      clearTimeout(voiceStatusTimer);
      voiceStatusTimer = null;
    }

    if (!message) {
      chatVoiceStatus.hidden = true;
      chatVoiceStatus.textContent = '';
      chatVoiceStatus.classList.remove('is-error');
      return;
    }

    const isError = options.isError === true;
    const withPermissionHelp = options.withPermissionHelp === true;
    const persist = options.persist === true;

    chatVoiceStatus.hidden = false;
    chatVoiceStatus.classList.toggle('is-error', isError);
    if (withPermissionHelp) {
      chatVoiceStatus.innerHTML = `
        <span>${escapeHtml(message)}</span>
        <a href="https://support.google.com/chrome/answer/2693767" target="_blank" rel="noopener">Autoriser le micro</a>
      `;
    } else {
      chatVoiceStatus.textContent = message;
    }

    if (!persist) {
      voiceStatusTimer = setTimeout(() => {
        setVoiceStatus('');
      }, 4200);
    }
  };

  const stopRecordingTimer = () => {
    if (recordingTimer) {
      clearInterval(recordingTimer);
      recordingTimer = null;
    }
  };

  const stopMediaStream = () => {
    if (!mediaStream) {
      return;
    }

    mediaStream.getTracks().forEach((track) => track.stop());
    mediaStream = null;
  };

  const revokeVoicePreviewUrl = () => {
    if (!voicePreviewUrl) {
      return;
    }

    URL.revokeObjectURL(voicePreviewUrl);
    voicePreviewUrl = null;
  };

  const stopActiveAudioPlayback = () => {
    if (!activeAudioElement) {
      return;
    }

    activeAudioElement.pause();
    activeAudioElement = null;
  };

  const updatePreviewPlayButton = (isPlaying) => {
    if (!chatVoicePreviewPlayBtn) {
      return;
    }

    chatVoicePreviewPlayBtn.innerHTML = isPlaying ? '&#10074;&#10074;' : '&#9654;';
    chatVoicePreviewPlayBtn.setAttribute('aria-label', isPlaying ? 'Mettre en pause' : 'Lire le message vocal');
  };

  const setVoiceRecorderState = (state) => {
    voiceRecorderState = state;
    const hasConversation = normalizeId(activeConversationId) !== null;
    const hasPreview = recordedAudioBlob instanceof Blob;
    const isRecording = state === 'recording';
    const isPreview = state === 'preview';
    const isSending = state === 'sending';

    if (chatVoiceRecorder) {
      chatVoiceRecorder.dataset.state = state;
      chatVoiceRecorder.classList.toggle('is-recording', isRecording);
      chatVoiceRecorder.classList.toggle('is-preview', isPreview);
      chatVoiceRecorder.classList.toggle('is-sending', isSending);
    }

    if (chatVoiceToggleBtn) {
      chatVoiceToggleBtn.classList.toggle('is-recording', isRecording);
    }

    if (chatVoiceStartBtn) {
      chatVoiceStartBtn.hidden = true;
      chatVoiceStartBtn.disabled = true;
    }

    if (chatVoiceStopBtn) {
      chatVoiceStopBtn.hidden = !isRecording;
      chatVoiceStopBtn.disabled = !isRecording || isSending;
    }

    if (chatVoicePreviewPlayBtn) {
      chatVoicePreviewPlayBtn.hidden = !isPreview;
      chatVoicePreviewPlayBtn.disabled = !isPreview || isSending || !hasPreview;
    }

    if (chatVoiceSendBtn) {
      chatVoiceSendBtn.disabled = !hasConversation || !hasPreview || isRecording || isSending;
      chatVoiceSendBtn.classList.toggle('is-loading', isSending);
    }

    if (chatVoiceCancelBtn) {
      chatVoiceCancelBtn.disabled = !hasConversation || isSending;
    }

    if (chatVoiceWave) {
      chatVoiceWave.classList.toggle('is-animated', isRecording);
    }
  };

  const refreshVoiceRecorderButtons = () => {
    setVoiceRecorderState(voiceRecorderState);
  };

  const clearVoicePreview = () => {
    revokeVoicePreviewUrl();
    if (chatVoicePreviewAudio) {
      if (activeAudioElement === chatVoicePreviewAudio) {
        activeAudioElement = null;
      }
      chatVoicePreviewAudio.pause();
      chatVoicePreviewAudio.removeAttribute('src');
      chatVoicePreviewAudio.load();
    }
    updatePreviewPlayButton(false);
  };

  const resetVoiceRecorder = (options = {}) => {
    const hidePanel = options.hidePanel === true;
    const stopRecorder = options.stopRecorder !== false;

    if (stopRecorder && mediaRecorder && mediaRecorder.state !== 'inactive') {
      ignoreNextRecorderStop = true;
      try {
        mediaRecorder.stop();
      } catch {}
    }

    stopRecordingTimer();
    stopMediaStream();
    stopActiveAudioPlayback();
    mediaRecorder = null;
    recordedAudioChunks = [];
    recordedAudioBlob = null;
    recordedAudioDurationSeconds = 0;
    recordingStartedAt = 0;
    selectedAudioMimeType = '';
    sendingVoiceMessage = false;
    recordingStoppedByLimit = false;
    if (!stopRecorder) {
      ignoreNextRecorderStop = false;
    }
    if (chatVoiceDuration) {
      chatVoiceDuration.textContent = '0:00';
    }
    if (chatVoiceRecorder) {
      if (hidePanel) {
        chatVoiceRecorder.hidden = true;
      }
    }

    if (options.keepStatus !== true) {
      setVoiceStatus('');
    }

    clearVoicePreview();
    setVoiceRecorderState('idle');
    refreshVoiceRecorderButtons();
  };

  const showVoicePreview = (blob) => {
    if (!chatVoicePreviewAudio) {
      return;
    }

    clearVoicePreview();
    voicePreviewUrl = URL.createObjectURL(blob);
    chatVoicePreviewAudio.src = voicePreviewUrl;
    chatVoicePreviewAudio.load();
    updatePreviewPlayButton(false);
    setVoiceRecorderState('preview');
  };

  const detectSupportedAudioMimeType = () => {
    if (typeof MediaRecorder === 'undefined' || typeof MediaRecorder.isTypeSupported !== 'function') {
      return '';
    }

    for (const mimeType of VOICE_MIME_TYPES) {
      if (MediaRecorder.isTypeSupported(mimeType)) {
        return mimeType;
      }
    }

    return '';
  };

  const openVoiceRecorder = () => {
    if (!chatVoiceRecorder) {
      return;
    }

    if (!activeConversationId) {
      setUploadStatus('Selectionnez une conversation.', true);
      return;
    }

    chatVoiceRecorder.hidden = false;
    setVoiceStatus('');
    if ((voiceRecorderState === 'idle' || voiceRecorderState === 'error') && !recordedAudioBlob) {
      void startVoiceRecording();
      return;
    }

    refreshVoiceRecorderButtons();
  };

  const closeVoiceRecorder = () => {
    resetVoiceRecorder({ hidePanel: true });
  };

  const startVoiceRecording = async () => {
    if (!activeConversationId) {
      setUploadStatus('Selectionnez une conversation.', true);
      return;
    }

    if (!navigator.mediaDevices || typeof navigator.mediaDevices.getUserMedia !== 'function' || typeof MediaRecorder === 'undefined') {
      setVoiceStatus('Votre navigateur ne supporte pas les messages vocaux.', { isError: true, persist: true });
      return;
    }

    resetVoiceRecorder({ hidePanel: false, stopRecorder: false });
    setUploadStatus('');
    setVoiceStatus('Enregistrement en cours...');
    selectedAudioMimeType = detectSupportedAudioMimeType();

    try {
      mediaStream = await navigator.mediaDevices.getUserMedia({ audio: true });
    } catch {
      setVoiceRecorderState('error');
      setVoiceStatus('Acces micro refuse. Autorisez le micro pour enregistrer.', {
        isError: true,
        withPermissionHelp: true,
        persist: true,
      });
      refreshVoiceRecorderButtons();
      return;
    }

    try {
      mediaRecorder = selectedAudioMimeType
        ? new MediaRecorder(mediaStream, { mimeType: selectedAudioMimeType })
        : new MediaRecorder(mediaStream);
    } catch {
      stopMediaStream();
      setVoiceRecorderState('error');
      setVoiceStatus('Impossible de demarrer l enregistrement audio.', { isError: true, persist: true });
      refreshVoiceRecorderButtons();
      return;
    }

    recordedAudioChunks = [];
    recordedAudioBlob = null;
    recordingStartedAt = Date.now();
    if (chatVoiceDuration) {
      chatVoiceDuration.textContent = '0:00';
    }

    mediaRecorder.ondataavailable = (event) => {
      if (event.data && event.data.size > 0) {
        recordedAudioChunks.push(event.data);
      }
    };

    mediaRecorder.onstop = () => {
      stopRecordingTimer();
      stopMediaStream();

      if (ignoreNextRecorderStop) {
        ignoreNextRecorderStop = false;
        refreshVoiceRecorderButtons();
        return;
      }

      if (recordedAudioChunks.length === 0) {
        setVoiceRecorderState('error');
        setVoiceStatus('Enregistrement vide.', { isError: true });
        refreshVoiceRecorderButtons();
        return;
      }

      recordedAudioDurationSeconds = Math.max(1, Math.floor((Date.now() - recordingStartedAt) / 1000));
      if (chatVoiceDuration) {
        chatVoiceDuration.textContent = formatDuration(recordedAudioDurationSeconds);
      }

      const mimeType = selectedAudioMimeType || mediaRecorder.mimeType || 'audio/webm';
      recordedAudioBlob = new Blob(recordedAudioChunks, { type: mimeType });
      showVoicePreview(recordedAudioBlob);
      if (recordingStoppedByLimit) {
        setVoiceStatus(`Duree max atteinte (${formatDuration(VOICE_MAX_SECONDS)}).`, { isError: true });
      } else {
        setVoiceStatus('Enregistre. Vous pouvez ecouter puis envoyer.');
      }
      recordingStoppedByLimit = false;
      refreshVoiceRecorderButtons();
    };

    mediaRecorder.start();
    setVoiceRecorderState('recording');
    updatePreviewPlayButton(false);

    recordingTimer = setInterval(() => {
      const elapsedSeconds = Math.floor((Date.now() - recordingStartedAt) / 1000);
      if (chatVoiceDuration) {
        chatVoiceDuration.textContent = formatDuration(elapsedSeconds);
      }

      if (elapsedSeconds >= VOICE_MAX_SECONDS) {
        stopVoiceRecording({ reason: 'max' });
      }
    }, 250);

    refreshVoiceRecorderButtons();
  };

  const stopVoiceRecording = (options = {}) => {
    if (!mediaRecorder || mediaRecorder.state !== 'recording') {
      return;
    }

    recordingStoppedByLimit = options.reason === 'max';
    mediaRecorder.stop();
    setVoiceRecorderState('preview');
    refreshVoiceRecorderButtons();
  };

  const toggleVoicePreviewPlayback = async () => {
    if (!chatVoicePreviewAudio || !chatVoicePreviewAudio.src) {
      return;
    }

    if (chatVoicePreviewAudio.paused) {
      if (activeAudioElement && activeAudioElement !== chatVoicePreviewAudio) {
        activeAudioElement.pause();
      }

      activeAudioElement = chatVoicePreviewAudio;
      try {
        await chatVoicePreviewAudio.play();
      } catch {
        setVoiceStatus('Impossible de lire ce message vocal.', { isError: true });
        return;
      }

      updatePreviewPlayButton(true);
      return;
    }

    chatVoicePreviewAudio.pause();
    updatePreviewPlayButton(false);
  };

  const sendVoiceMessage = async () => {
    if (!activeConversationId || !recordedAudioBlob || !urls.messageAudioCreate) {
      return;
    }

    const duration = Math.max(1, Math.floor(recordedAudioDurationSeconds || 0));
    if (duration > VOICE_MAX_SECONDS) {
      setVoiceStatus(`Le message vocal depasse ${formatDuration(VOICE_MAX_SECONDS)}.`, { isError: true });
      return;
    }

    sendingVoiceMessage = true;
    setVoiceRecorderState('sending');
    setUploadStatus('Envoi du message vocal...');
    setVoiceStatus('Envoi en cours...');

    const extension = recordedAudioBlob.type.includes('ogg')
      ? 'ogg'
      : recordedAudioBlob.type.includes('mp4')
        ? 'm4a'
        : 'webm';
    const formData = new FormData();
    formData.append('audio', recordedAudioBlob, `voice-message.${extension}`);
    formData.append('duration', String(duration));
    formData.append('durationMs', String(duration * 1000));
    formData.append('_token', csrf);

    try {
      const response = await apiFetch(resolveUrl(urls.messageAudioCreate, activeConversationId), {
        method: 'POST',
        headers: { Accept: 'application/json' },
        body: formData,
      });
      const payload = await readJsonSafe(response);
      if (!response.ok) {
        setUploadStatus(payload?.error || 'Echec de l envoi du message vocal.', true);
        setVoiceStatus(payload?.error || 'Echec de l envoi du message vocal.', { isError: true, persist: true });
        sendingVoiceMessage = false;
        setVoiceRecorderState('preview');
        return;
      }

      const createdMessage = payload?.message || null;
      if (createdMessage && createdMessage.id) {
        addMessageToDom(createdMessage);
        if (!updateConversationActivity(activeConversationId, createdMessage)) {
          const upserted = await fetchAndUpsertConversation(activeConversationId);
          if (!upserted) {
            await loadConversations(true);
          }
        }
        if (chatMessagesEl) {
          chatMessagesEl.scrollTop = chatMessagesEl.scrollHeight;
        }
      } else {
        await reloadActiveConversationMessages();
        const upserted = await fetchAndUpsertConversation(activeConversationId);
        if (!upserted) {
          await loadConversations(true);
        }
      }

      setUploadStatus('');
      setVoiceStatus('Message vocal envoye.');
      closeVoiceRecorder();
    } catch {
      setUploadStatus('Erreur reseau pendant l envoi du message vocal.', true);
      setVoiceStatus('Erreur reseau pendant l envoi du message vocal.', { isError: true, persist: true });
      sendingVoiceMessage = false;
      setVoiceRecorderState('preview');
    }
  };

  const getConversationById = (id) => lastConversations.find((item) => Number(item.id) === Number(id));

  const getConversationActivityTimestamp = (conversation) => {
    if (!conversation || !conversation.lastMessageAt) {
      return 0;
    }

    const ts = Date.parse(conversation.lastMessageAt);
    return Number.isNaN(ts) ? 0 : ts;
  };

  const sortConversationsByActivity = (items) => {
    items.sort((a, b) => {
      const diff = getConversationActivityTimestamp(b) - getConversationActivityTimestamp(a);
      if (diff !== 0) {
        return diff;
      }

      return Number(b.id || 0) - Number(a.id || 0);
    });
  };

  const updateConversationHeaderActions = () => {
    const active = getConversationById(activeConversationId);

    if (hideConversationMenuItem) {
      hideConversationMenuItem.hidden = !active;
    }

    if (leaveGroupMenuItem) {
      leaveGroupMenuItem.hidden = !(active && active.isGroup);
    }

    if (manageMembersMenuItem) {
      manageMembersMenuItem.hidden = !(active && active.isGroup && active.isAdmin);
    }
  };

  const clearActiveConversationSelection = () => {
    const previousConversationId = activeConversationId;
    if (previousConversationId) {
      stopLocalTyping({ conversationId: previousConversationId, force: true });
    }

    activeConversationId = null;
    editingMessageId = null;

    if (chatTitleEl) chatTitleEl.textContent = 'Selectionnez une conversation';
    if (chatMessagesEl) chatMessagesEl.innerHTML = '';
    if (editBanner) editBanner.hidden = true;
    if (chatInputEl) chatInputEl.value = '';
    if (chatFileInput) chatFileInput.value = '';
    if (chatUploadPreview) {
      chatUploadPreview.hidden = true;
      chatUploadPreview.innerHTML = '';
    }
    stopActiveAudioPlayback();
    closeVoiceRecorder();

    setUploadStatus('');
    setInputEnabled(false);
    updateConversationHeaderActions();
    renderTypingIndicators();

    if (urls.chatIndex) {
      history.replaceState({}, '', urls.chatIndex);
    }
  };

  const refreshConversationList = () => {
    // Keep sidebar sorted by latest message activity without reloading from server.
    sortConversationsByActivity(lastConversations);
    renderConversations(lastConversations);
    updateConversationHeaderActions();
  };

  const updateConversationActivity = (conversationId, message) => {
    const item = getConversationById(conversationId);
    if (!item) {
      return false;
    }

    item.lastMessage = buildMessagePreview(message);
    item.lastMessageAt = message?.createdAt || new Date().toISOString();

    refreshConversationList();
    return true;
  };

  const upsertConversation = (conversation) => {
    if (!conversation || !conversation.id) {
      return false;
    }

    const conversationId = Number(conversation.id);
    const existingIndex = lastConversations.findIndex((item) => Number(item.id) === conversationId);
    if (existingIndex >= 0) {
      lastConversations[existingIndex] = {
        ...lastConversations[existingIndex],
        ...conversation,
      };
    } else {
      lastConversations.push(conversation);
    }

    refreshConversationList();
    return true;
  };

  const fetchAndUpsertConversation = async (conversationId) => {
    if (!urls.conversationSummary || !conversationId) {
      return false;
    }

    const response = await fetch(resolveUrl(urls.conversationSummary, conversationId));
    if (!response.ok) {
      return false;
    }

    const payload = await readJsonSafe(response);
    return upsertConversation(payload);
  };

  const renderConversations = (items) => {
    if (!conversationListEl) {
      return;
    }

    conversationListEl.innerHTML = '';
    if (!items.length) {
      conversationListEl.innerHTML = '<div class="chat-empty">Aucune conversation</div>';
      return;
    }

    items.forEach((item) => {
      const node = document.createElement('button');
      node.type = 'button';
      node.className = 'chat-conversation-item';
      node.dataset.id = item.id;
      if (activeConversationId === Number(item.id)) {
        node.classList.add('active');
      }

      const badge = item.isGroup ? '<span class="chat-conversation-badge">Groupe</span>' : '';
      node.innerHTML = `
        <div class="chat-conversation-title">
          <span>${escapeHtml(item.title || 'Conversation')}</span>
          ${badge}
        </div>
        <div class="chat-conversation-snippet">${escapeHtml(item.lastMessage || '')}</div>
        <div class="chat-conversation-time">${formatTime(item.lastMessageAt)}</div>
      `;

      node.addEventListener('click', () => {
        selectConversation(item.id, item.title);
      });

      conversationListEl.appendChild(node);
    });
  };

  const loadConversations = async (keepSelection = true) => {
    if (!urls.conversations) {
      return;
    }

    const query = (chatSearchEl?.value || '').trim();
    const targetUrl = query ? `${urls.conversations}?q=${encodeURIComponent(query)}` : urls.conversations;

    const response = await fetch(targetUrl);
    if (!response.ok) {
      if (conversationListEl) {
        conversationListEl.innerHTML = '<div class="chat-empty">Impossible de charger les conversations.</div>';
      }
      return;
    }
    const data = await readJsonSafe(response);
    lastConversations = Array.isArray(data) ? data : [];
    sortConversationsByActivity(lastConversations);
    renderConversations(lastConversations);

    if (!keepSelection) {
      clearActiveConversationSelection();
      return;
    }

    if (!initialApplied && initialConversationId) {
      const initialConversation = getConversationById(initialConversationId);
      if (initialConversation) {
        initialApplied = true;
        await selectConversation(initialConversation.id, initialConversation.title);
        return;
      }
    }

    if (activeConversationId) {
      const activeConversation = getConversationById(activeConversationId);
      if (!activeConversation) {
        clearActiveConversationSelection();
      } else if (conversationListEl) {
        Array.from(conversationListEl.querySelectorAll('.chat-conversation-item')).forEach((item) => {
          item.classList.toggle('active', Number(item.dataset.id) === Number(activeConversationId));
        });
      }
    }

    updateConversationHeaderActions();
  };

  const getMessageAttachments = (message) => {
    if (Array.isArray(message?.attachments) && message.attachments.length > 0) {
      return message.attachments.filter((attachment) => attachment && attachment.url);
    }

    // Backward compatibility for legacy messages.
    if (message?.filePath) {
      return [{
        id: null,
        name: 'Fichier',
        mimeType: message.type === 'image'
          ? 'image/*'
          : message.type === 'audio'
            ? 'audio/webm'
            : 'application/octet-stream',
        size: 0,
        isImage: message.type === 'image',
        type: message.type === 'audio' ? 'audio' : (message.type === 'image' ? 'image' : 'file'),
        duration: Number(message.duration || 0) > 0 ? Number(message.duration) : null,
        url: message.filePath,
        downloadUrl: message.filePath,
      }];
    }

    return [];
  };

  const buildAudioWaveBarsHtml = (count = 22) => {
    const heights = [34, 45, 58, 42, 64, 39, 50, 70, 46, 62, 36, 55];
    let html = '';
    for (let index = 0; index < count; index += 1) {
      const value = heights[index % heights.length];
      html += `<span style="height:${value}%"></span>`;
    }

    return html;
  };

  const setupAudioWidget = (widget) => {
    if (!(widget instanceof HTMLElement) || widget.dataset.bound === '1') {
      return;
    }

    const audio = widget.querySelector('.chat-audio-element');
    const playButton = widget.querySelector('.chat-audio-play-btn');
    const waveTrack = widget.querySelector('.chat-audio-wave-track');
    const progress = widget.querySelector('.chat-audio-wave-progress');
    const durationLabel = widget.querySelector('.chat-audio-time');
    if (!(audio instanceof HTMLAudioElement) || !(playButton instanceof HTMLButtonElement)) {
      return;
    }

    const baseDuration = Number(widget.dataset.duration || 0);
    const applyDuration = () => {
      if (!(durationLabel instanceof HTMLElement)) {
        return;
      }

      const duration = Number.isFinite(audio.duration) && audio.duration > 0
        ? audio.duration
        : baseDuration;
      durationLabel.textContent = formatDuration(duration || 0);
    };

    const applyProgress = () => {
      if (!(progress instanceof HTMLElement)) {
        return;
      }

      const duration = Number.isFinite(audio.duration) && audio.duration > 0 ? audio.duration : 0;
      const ratio = duration > 0 ? Math.min(1, Math.max(0, audio.currentTime / duration)) : 0;
      progress.style.width = `${Math.round(ratio * 100)}%`;
    };

    const setPlaying = (isPlaying) => {
      playButton.innerHTML = isPlaying ? '&#10074;&#10074;' : '&#9654;';
      playButton.setAttribute('aria-label', isPlaying ? 'Mettre en pause' : 'Lire le message vocal');
      widget.classList.toggle('is-playing', isPlaying);
    };

    playButton.addEventListener('click', async () => {
      if (audio.paused) {
        if (activeAudioElement && activeAudioElement !== audio) {
          activeAudioElement.pause();
        }

        activeAudioElement = audio;
        try {
          await audio.play();
        } catch {
          return;
        }

        setPlaying(true);
        return;
      }

      audio.pause();
      setPlaying(false);
    });

    if (waveTrack instanceof HTMLElement) {
      waveTrack.addEventListener('click', (event) => {
        const rect = waveTrack.getBoundingClientRect();
        if (rect.width <= 0) {
          return;
        }

        const duration = Number.isFinite(audio.duration) && audio.duration > 0 ? audio.duration : 0;
        if (duration <= 0) {
          return;
        }

        const ratio = Math.min(1, Math.max(0, (event.clientX - rect.left) / rect.width));
        audio.currentTime = duration * ratio;
        applyProgress();
      });
    }

    audio.addEventListener('loadedmetadata', () => {
      applyDuration();
      applyProgress();
    });
    audio.addEventListener('timeupdate', applyProgress);
    audio.addEventListener('ended', () => {
      setPlaying(false);
      applyProgress();
      if (activeAudioElement === audio) {
        activeAudioElement = null;
      }
    });
    audio.addEventListener('pause', () => {
      setPlaying(false);
      if (activeAudioElement === audio) {
        activeAudioElement = null;
      }
    });
    audio.addEventListener('play', () => {
      setPlaying(true);
      activeAudioElement = audio;
    });

    applyDuration();
    applyProgress();
    setPlaying(false);
    widget.dataset.bound = '1';
  };

  const setupAudioWidgetsIn = (container) => {
    if (!container) {
      return;
    }

    const scope = container instanceof Element ? container : document;
    scope.querySelectorAll('.js-audio-widget').forEach((node) => {
      setupAudioWidget(node);
    });
  };

  const renderAudioAttachmentHtml = (attachment) => {
    const safeUrl = escapeHtml(attachment.url || '#');
    const duration = Number(attachment.duration || 0);
    const durationLabel = formatDuration(duration > 0 ? duration : 0);

    return `
      <div class="chat-audio-widget js-audio-widget" data-duration="${duration > 0 ? duration : 0}">
        <button type="button" class="chat-audio-play-btn" aria-label="Lire le message vocal">&#9654;</button>
        <div class="chat-audio-wave-track" aria-hidden="true">
          <div class="chat-audio-wave-bars">${buildAudioWaveBarsHtml()}</div>
          <div class="chat-audio-wave-progress"></div>
        </div>
        <span class="chat-audio-time">${escapeHtml(durationLabel)}</span>
        <audio class="chat-audio-element" preload="metadata" src="${safeUrl}"></audio>
      </div>
    `;
  };

  const renderAttachmentHtml = (attachment) => {
    if (isAudioAttachment(attachment)) {
      return renderAudioAttachmentHtml(attachment);
    }

    const safeName = escapeHtml(attachment.name || 'Fichier');
    const safeUrl = escapeHtml(attachment.url || '#');
    const safeDownloadUrl = escapeHtml(attachment.downloadUrl || attachment.url || '#');
    const safeMime = escapeHtml(attachment.mimeType || 'application/octet-stream');
    const fileSize = formatFileSize(attachment.size);

    if (attachment.isImage) {
      return `
        <a class="chat-attachment-thumb" href="${safeUrl}" target="_blank" rel="noopener" title="${safeName}">
          <img src="${safeUrl}" alt="${safeName}" loading="lazy">
        </a>
      `;
    }

    const attachmentId = normalizeId(attachment.id);
    const canSummarize = attachmentId !== null && isSummarizableAttachment(attachment);
    const summaryState = attachmentId !== null ? attachmentSummaryState.get(attachmentId) : null;
    const hasLoadedSummary = Boolean(summaryState?.loaded && summaryState?.text);
    const isSummaryExpanded = Boolean(summaryState?.expanded);
    const summaryButtonLabel = isSummaryExpanded ? 'Masquer le resume' : 'Resumer le fichier';
    const summaryContent = hasLoadedSummary
      ? `<div class="chat-attachment-summary-content">${escapeHtml(String(summaryState.text)).replace(/\n/g, '<br>')}</div>`
      : '';
    const summaryHiddenAttr = isSummaryExpanded ? '' : ' hidden';

    return `
      <div class="chat-attachment-file-wrap" data-attachment-id="${attachmentId ?? ''}">
        <a class="chat-attachment-file" href="${safeDownloadUrl}" target="_blank" rel="noopener">
          <span class="chat-attachment-icon">📄</span>
          <span class="chat-attachment-meta">
            <span class="chat-attachment-name">${safeName}</span>
            <span class="chat-attachment-details">${safeMime} · ${fileSize}</span>
          </span>
        </a>
        ${canSummarize ? `
          <div class="chat-attachment-actions">
            <button
              type="button"
              class="chat-attachment-summary-btn js-summarize-attachment"
              data-attachment-id="${attachmentId}"
            >
              ${summaryButtonLabel}
            </button>
          </div>
          <div class="chat-attachment-summary summary-container" data-attachment-id="${attachmentId}"${summaryHiddenAttr}>${summaryContent}</div>
        ` : ''}
      </div>
    `;
  };

  const renderSummaryContainer = (container, text, isError = false) => {
    if (!(container instanceof HTMLElement)) {
      return;
    }

    if (isError) {
      container.innerHTML = `<div class="chat-attachment-summary-error">${escapeHtml(text)}</div>`;
    } else {
      const formatted = escapeHtml(text).replace(/\n/g, '<br>');
      container.innerHTML = `<div class="chat-attachment-summary-content">${formatted}</div>`;
    }

    container.hidden = false;
  };

  const setSummaryButtonState = (button, state) => {
    if (!(button instanceof HTMLButtonElement)) {
      return;
    }

    const loading = Boolean(state.loading);
    const expanded = Boolean(state.expanded);
    button.disabled = loading;
    button.classList.toggle('is-loading', loading);

    if (loading) {
      button.textContent = 'Generation...';
      return;
    }

    button.textContent = expanded ? 'Masquer le resume' : 'Resumer le fichier';
  };

  const fetchAttachmentSummary = async (attachmentId) => {
    if (!urls.attachmentSummarize) {
      throw new Error('Endpoint de resume non configure.');
    }

    const response = await apiFetch(resolveUrl(urls.attachmentSummarize, attachmentId), {
      method: 'POST',
    });
    const payload = await readJsonSafe(response);

    if (!response.ok) {
      throw new Error(payload?.error || 'Impossible de generer le resume.');
    }

    const summaryText = String(payload?.summaryText || '').trim();
    if (!summaryText) {
      throw new Error('Resume vide retourne par le serveur.');
    }

    return summaryText;
  };

  const handleAttachmentSummaryToggle = async (button) => {
    if (!(button instanceof HTMLButtonElement)) {
      return;
    }

    const attachmentId = normalizeId(button.dataset.attachmentId);
    if (attachmentId === null) {
      return;
    }

    const wrapper = button.closest('.chat-attachment-file-wrap');
    const container = wrapper ? wrapper.querySelector('.summary-container') : null;
    if (!(container instanceof HTMLElement)) {
      return;
    }

    const state = attachmentSummaryState.get(attachmentId) || {
      loading: false,
      loaded: false,
      expanded: false,
      text: '',
    };

    if (state.loading) {
      return;
    }

    if (state.loaded) {
      if (state.text && container.innerHTML.trim() === '') {
        renderSummaryContainer(container, state.text);
      }

      state.expanded = !state.expanded;
      attachmentSummaryState.set(attachmentId, state);
      container.hidden = !state.expanded;
      setSummaryButtonState(button, state);
      return;
    }

    state.loading = true;
    state.expanded = true;
    attachmentSummaryState.set(attachmentId, state);
    setSummaryButtonState(button, state);
    renderSummaryContainer(container, 'Generation...');

    try {
      const summaryText = await fetchAttachmentSummary(attachmentId);
      state.loading = false;
      state.loaded = true;
      state.text = summaryText;
      state.expanded = true;
      attachmentSummaryState.set(attachmentId, state);

      renderSummaryContainer(container, summaryText);
      setSummaryButtonState(button, state);
    } catch (error) {
      state.loading = false;
      state.loaded = false;
      state.text = '';
      state.expanded = true;
      attachmentSummaryState.set(attachmentId, state);

      const message = error instanceof Error ? error.message : 'Impossible de generer le resume.';
      renderSummaryContainer(container, message, true);
      setSummaryButtonState(button, state);
    }
  };

  const getMessageBody = (message) => {
    const content = String(message?.content || '').trim();
    const attachments = getMessageAttachments(message);

    const contentHtml = content !== '' ? `<div class="chat-message-text">${escapeHtml(content)}</div>` : '';
    const audio = attachments.filter((attachment) => isAudioAttachment(attachment));
    const images = attachments.filter((attachment) => Boolean(attachment?.isImage) && !isAudioAttachment(attachment));
    const files = attachments.filter((attachment) => !attachment?.isImage && !isAudioAttachment(attachment));

    const audioBlock = audio.length > 0
      ? `<div class="chat-audio-attachments">${audio.map(renderAttachmentHtml).join('')}</div>`
      : '';
    const imageBlock = images.length > 0
      ? `<div class="chat-attachments-grid">${images.map(renderAttachmentHtml).join('')}</div>`
      : '';
    const fileBlock = files.length > 0
      ? `<div class="chat-attachments-files">${files.map(renderAttachmentHtml).join('')}</div>`
      : '';

    return `${contentHtml}${audioBlock}${imageBlock}${fileBlock}`;
  };

  const isOwnMessage = (message) => {
    const senderId = normalizeId(message?.senderId);
    return userId !== null && senderId !== null && senderId === userId;
  };

  const isActiveGroupConversation = () => {
    const active = getConversationById(activeConversationId);
    return Boolean(active && active.isGroup);
  };

  const isGroupedWithPrevious = (message, previousMessage = null) => {
    const senderId = normalizeId(message?.senderId);
    if (senderId === null) {
      return false;
    }

    if (previousMessage) {
      return senderId === normalizeId(previousMessage?.senderId);
    }

    if (!chatMessagesEl) {
      return false;
    }

    const previousNode = chatMessagesEl.lastElementChild;
    if (!(previousNode instanceof HTMLElement)) {
      return false;
    }

    const previousSenderId = normalizeId(previousNode.dataset.senderId);
    return previousSenderId !== null && previousSenderId === senderId;
  };

  const updateMessageDom = (message) => {
    if (!chatMessagesEl) {
      return;
    }

    syncMessageTextCache(message);
    const wrapper = chatMessagesEl.querySelector(`[data-id="${message.id}"]`);
    if (!wrapper) {
      return;
    }

    const bubble = wrapper.querySelector('.chat-bubble');
    if (bubble) {
      bubble.innerHTML = getMessageBody(message);
      setupAudioWidgetsIn(wrapper);
    }

    renderMessageTranslationUi(wrapper, message.id);
  };

  const addMessageToDom = (message, options = {}) => {
    if (!chatMessagesEl) {
      return;
    }

    syncMessageTextCache(message);
    const existing = chatMessagesEl.querySelector(`[data-id="${message.id}"]`);
    if (existing) {
      updateMessageDom(message);
      return;
    }

    const wrapper = document.createElement('div');
    wrapper.className = 'chat-message';
    wrapper.dataset.id = message.id;
    const ownMessage = isOwnMessage(message);
    const groupedWithPrevious = options.groupedWithPrevious === true
      ? true
      : options.groupedWithPrevious === false
        ? false
        : isGroupedWithPrevious(message, options.previousMessage || null);
    const groupConversation = isActiveGroupConversation();
    const showSenderName = groupConversation && !ownMessage && !groupedWithPrevious;

    wrapper.classList.add(ownMessage ? 'chat-message--out' : 'chat-message--in');
    wrapper.classList.add(ownMessage ? 'msg--mine' : 'msg--other');
    if (groupedWithPrevious) {
      wrapper.classList.add('chat-message--grouped');
    }
    wrapper.dataset.senderId = String(normalizeId(message?.senderId) ?? '');

    wrapper.innerHTML = `
      <div class="chat-message-row">
        <div class="chat-message-content">
          ${showSenderName ? `<div class="chat-sender-name">${escapeHtml(message.senderName || 'Utilisateur')}</div>` : ''}
          <div class="chat-bubble">${getMessageBody(message)}</div>
          <div class="chat-message-meta">${formatTime(message.createdAt)}</div>
          <div class="chat-message-translate">
            <div class="chat-translate-actions js-translate-actions"></div>
            <div class="chat-translate-status js-translate-status" hidden></div>
            <div class="chat-translation-list js-translation-list"></div>
          </div>
        </div>
      </div>
      ${ownMessage ? '<button class="chat-message-menu" type="button">...</button>' : ''}
      ${ownMessage ? `
        <div class="chat-message-actions">
          <button type="button" data-action="edit">Modifier</button>
          <button type="button" data-action="delete">Supprimer</button>
        </div>
      ` : ''}
    `;

    const menuButton = wrapper.querySelector('.chat-message-menu');
    const actionList = wrapper.querySelector('.chat-message-actions');

    if (menuButton && actionList) {
      menuButton.addEventListener('click', (event) => {
        event.stopPropagation();
        actionList.classList.toggle('open');
      });

      actionList.addEventListener('click', async (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement)) {
          return;
        }

        const action = target.dataset.action;
        if (!action) {
          return;
        }

        actionList.classList.remove('open');

        if (action === 'edit') {
          editingMessageId = message.id;
          if (chatInputEl) {
            chatInputEl.value = message.content || '';
            chatInputEl.focus();
          }
          if (editBanner) editBanner.hidden = false;
          return;
        }

        if (action === 'delete') {
          const response = await apiFetch(resolveUrl(urls.messageDelete, message.id), {
            method: 'DELETE',
          });

          const data = await readJsonSafe(response);
          if (response.ok && data && data.id) {
            updateMessageDom(data);
          }
        }
      });
    }

    chatMessagesEl.appendChild(wrapper);
    setupAudioWidgetsIn(wrapper);
    renderMessageTranslationUi(wrapper, message.id);
  };

  const renderMessages = (messages) => {
    if (!chatMessagesEl) {
      return;
    }

    chatMessagesEl.innerHTML = '';
    messages.forEach((message, index) => {
      const previousMessage = index > 0 ? messages[index - 1] : null;
      addMessageToDom(message, { previousMessage });
    });
    setupAudioWidgetsIn(chatMessagesEl);
    chatMessagesEl.scrollTop = chatMessagesEl.scrollHeight;
  };

  const reloadActiveConversationMessages = async () => {
    const conversationId = normalizeId(activeConversationId);
    if (conversationId === null || !urls.messages) {
      return;
    }

    const response = await fetch(buildMessagesUrl(conversationId));
    if (!response.ok) {
      return;
    }

    const payload = await readJsonSafe(response);
    const items = payload && Array.isArray(payload.items) ? payload.items : [];
    renderMessages(items);
  };

  const selectConversation = async (conversationId, title) => {
    const nextConversationId = Number(conversationId);
    const previousConversationId = activeConversationId;
    if (previousConversationId) {
      stopLocalTyping({ conversationId: previousConversationId, force: true });
    }

    if (membersModal && !membersModal.hidden) {
      closeMembersModal();
    }
    stopActiveAudioPlayback();

    activeConversationId = nextConversationId;
    const found = getConversationById(activeConversationId);

    if (chatTitleEl) chatTitleEl.textContent = title || found?.title || 'Conversation';
    setInputEnabled(true);
    updateConversationHeaderActions();

    editingMessageId = null;
    if (editBanner) editBanner.hidden = true;
    if (chatInputEl) chatInputEl.value = '';
    if (chatFileInput) chatFileInput.value = '';
    if (chatUploadPreview) {
      chatUploadPreview.hidden = true;
      chatUploadPreview.innerHTML = '';
    }
    closeVoiceRecorder();
    setUploadStatus('');
    renderTypingIndicators();

    const response = await fetch(buildMessagesUrl(activeConversationId));
    const payload = await readJsonSafe(response);
    const items = payload && Array.isArray(payload.items) ? payload.items : [];
    renderMessages(items);

    if (conversationListEl) {
      Array.from(conversationListEl.querySelectorAll('.chat-conversation-item')).forEach((item) => {
        item.classList.toggle('active', Number(item.dataset.id) === activeConversationId);
      });
    }

    if (urls.chatShow) {
      history.replaceState({}, '', resolveUrl(urls.chatShow, activeConversationId));
    }
  };

  const sendMessage = async () => {
    if (!activeConversationId || !chatInputEl || !chatFileInput) {
      return;
    }

    const content = chatInputEl.value.trim();
    const selectedFiles = Array.from(chatFileInput.files || []);
    if (!content && selectedFiles.length === 0) {
      return;
    }

    stopLocalTyping({ force: true });

    if (editingMessageId) {
      if (selectedFiles.length > 0) {
        setUploadStatus('Les pieces jointes ne peuvent pas etre ajoutees en mode modification.', true);
        return;
      }

      const response = await apiFetch(resolveUrl(urls.messageUpdate, editingMessageId), {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrf,
        },
        body: JSON.stringify({ content }),
      });

      const data = await readJsonSafe(response);
      if (response.ok && data && data.id) {
        updateMessageDom(data);
      }

      editingMessageId = null;
      if (editBanner) editBanner.hidden = true;
      chatInputEl.value = '';
      setUploadStatus('');
      return;
    }

    const formData = new FormData();
    formData.append('content', content);
    formData.append('_token', csrf);
    selectedFiles.forEach((file) => {
      formData.append('attachments[]', file);
    });

    setUploadStatus(
      selectedFiles.length > 0
        ? `Upload en cours (${selectedFiles.length} fichier${selectedFiles.length > 1 ? 's' : ''})...`
        : 'Envoi du message...'
    );

    if (chatSendBtn) {
      chatSendBtn.disabled = true;
    }

    try {
      const response = await apiFetch(resolveUrl(urls.messageCreate, activeConversationId), {
        method: 'POST',
        headers: { Accept: 'application/json' },
        body: formData,
      });
      const data = await readJsonSafe(response);

      if (!response.ok) {
        setUploadStatus((data && data.error) ? data.error : 'Echec de l\'envoi du message.', true);
        return;
      }

      const createdMessage = data && data.id
        ? data
        : (data && data.message && data.message.id ? data.message : null);

      if (createdMessage && createdMessage.id) {
        addMessageToDom(createdMessage);
        if (!updateConversationActivity(activeConversationId, createdMessage)) {
          const upserted = await fetchAndUpsertConversation(activeConversationId);
          if (!upserted) {
            await loadConversations(true);
          }
        }
        if (chatMessagesEl) {
          chatMessagesEl.scrollTop = chatMessagesEl.scrollHeight;
        }
      } else {
        await reloadActiveConversationMessages();
        const upserted = await fetchAndUpsertConversation(activeConversationId);
        if (!upserted) {
          await loadConversations(true);
        }
      }
    } catch {
      setUploadStatus('Erreur reseau pendant l\'upload.', true);
      return;
    } finally {
      if (chatSendBtn) {
        chatSendBtn.disabled = false;
      }
    }

    chatInputEl.value = '';
    chatFileInput.value = '';
    if (chatUploadPreview) {
      chatUploadPreview.hidden = true;
      chatUploadPreview.innerHTML = '';
    }
    setUploadStatus('');
  };

  const openImagesModal = async () => {
    if (!activeConversationId || !imagesGrid || !imagesModal) {
      return;
    }

    const response = await fetch(resolveUrl(urls.conversationImages, activeConversationId));
    const images = await readJsonSafe(response);
    imagesGrid.innerHTML = '';

    (Array.isArray(images) ? images : []).forEach((imageMessage) => {
      const attachments = getMessageAttachments(imageMessage).filter((attachment) => Boolean(attachment?.isImage));
      attachments.forEach((attachment) => {
        const link = document.createElement('a');
        link.href = attachment.url;
        link.target = '_blank';
        link.rel = 'noopener';

        const image = document.createElement('img');
        image.src = attachment.url;
        image.alt = attachment.name || 'image';
        link.appendChild(image);
        imagesGrid.appendChild(link);
      });
    });

    imagesModal.hidden = false;
  };

  const closeImagesModal = () => {
    if (imagesModal) {
      imagesModal.hidden = true;
    }
  };

  const renderMembersSearchResults = (results, query, canManage) => {
    if (!membersSearchResultsEl) {
      return;
    }

    membersSearchResultsEl.innerHTML = '';

    if (!canManage) {
      membersSearchResultsEl.innerHTML = '<div class="chat-inline-hint">Seul un admin peut ajouter des membres.</div>';
      return;
    }

    if (!query || query.length < 2) {
      membersSearchResultsEl.innerHTML = '<div class="chat-inline-hint">Entrez au moins 2 caracteres.</div>';
      return;
    }

    if (!results.length) {
      membersSearchResultsEl.innerHTML = '<div class="chat-inline-hint">Aucun parent trouve.</div>';
      return;
    }

    results.forEach((parent) => {
      const alreadyMember = activeGroupMemberIds.has(Number(parent.id));
      const node = document.createElement('div');
      node.className = 'chat-parent-item';
      node.innerHTML = `
        <div class="chat-parent-meta">
          <div class="chat-parent-name">${escapeHtml(parent.name || parent.email || 'Parent')}</div>
          <div class="chat-parent-email">${escapeHtml(parent.email || '')}</div>
        </div>
        <button class="chat-parent-select-btn ${alreadyMember ? 'is-selected' : ''}" type="button" data-member-add-id="${parent.id}" ${alreadyMember ? 'disabled' : ''}>
          ${alreadyMember ? 'Deja membre' : 'Ajouter'}
        </button>
      `;
      membersSearchResultsEl.appendChild(node);
    });
  };

  const renderMembersList = (items, canManage) => {
    if (!membersListEl) {
      return;
    }

    membersListEl.innerHTML = '';
    activeGroupMemberIds = new Set((items || []).map((item) => Number(item.id)));

    if (!items || !items.length) {
      membersListEl.innerHTML = '<div class="chat-inline-hint">Aucun membre.</div>';
      return;
    }

    items.forEach((member) => {
      const isSelf = Boolean(member.isCurrentUser);
      const isAdmin = member.role === 'admin';
      const canRemove = canManage && !isSelf;

      const node = document.createElement('div');
      node.className = 'chat-member-item';
      node.innerHTML = `
        <div class="chat-member-meta">
          <div class="chat-member-name">${escapeHtml(member.name || member.email || 'Utilisateur')}</div>
          <div class="chat-member-role">${isAdmin ? 'Admin' : 'Membre'}${isSelf ? ' (vous)' : ''}</div>
        </div>
        <div class="chat-member-actions">
          ${canRemove ? `<button type="button" class="chat-member-remove-btn" data-member-remove-id="${member.id}">Retirer</button>` : ''}
        </div>
      `;
      membersListEl.appendChild(node);
    });
  };

  const loadGroupMembers = async () => {
    const active = getConversationById(activeConversationId);
    if (!activeConversationId || !active || !active.isGroup || !membersListEl) {
      return;
    }

    membersLoading = true;
    membersListEl.innerHTML = '<div class="chat-inline-hint">Chargement...</div>';

    const response = await fetch(resolveUrl(urls.groupMembersList, activeConversationId));
    const payload = await readJsonSafe(response);

    membersLoading = false;
    if (!response.ok || !payload || !Array.isArray(payload.items)) {
      membersListEl.innerHTML = '<div class="chat-inline-hint">Impossible de charger les membres.</div>';
      return;
    }

    renderMembersList(payload.items, Boolean(payload.canManage));
    renderMembersSearchResults(
      membersSearchResults,
      (membersSearchInput?.value || '').trim(),
      Boolean(payload.canManage)
    );
  };

  const openMembersModal = async () => {
    const active = getConversationById(activeConversationId);
    if (!membersModal || !active || !active.isGroup || !active.isAdmin) {
      return;
    }

    setMembersError('');
    membersSearchResults = [];
    activeGroupMemberIds = new Set();
    if (membersSearchInput) {
      membersSearchInput.value = '';
    }
    if (membersSearchResultsEl) {
      membersSearchResultsEl.innerHTML = '<div class="chat-inline-hint">Entrez au moins 2 caracteres.</div>';
    }

    membersModal.hidden = false;
    await loadGroupMembers();

    if (membersSearchInput) {
      membersSearchInput.focus();
    }
  };

  const closeMembersModal = () => {
    if (membersModal) {
      membersModal.hidden = true;
    }
    setMembersError('');
    membersSearchResults = [];
    activeGroupMemberIds = new Set();
    if (membersSearchInput) {
      membersSearchInput.value = '';
    }
    if (membersSearchResultsEl) {
      membersSearchResultsEl.innerHTML = '<div class="chat-inline-hint">Entrez au moins 2 caracteres.</div>';
    }
  };

  const searchParentsForGroupMembers = async () => {
    if (!membersSearchInput || !membersSearchResultsEl) {
      return;
    }

    const query = membersSearchInput.value.trim();
    const active = getConversationById(activeConversationId);
    const canManage = Boolean(active && active.isGroup && active.isAdmin);

    if (query.length < 2) {
      membersSearchResults = [];
      renderMembersSearchResults(membersSearchResults, query, canManage);
      return;
    }

    const response = await fetch(`${urls.parentSearch}?q=${encodeURIComponent(query)}`);
    const data = await readJsonSafe(response);
    membersSearchResults = Array.isArray(data) ? data : [];
    renderMembersSearchResults(membersSearchResults, query, canManage);
  };

  const addMemberToActiveGroup = async (memberId) => {
    if (!activeConversationId || !memberId) {
      return;
    }

    setMembersError('');
    const response = await apiFetch(resolveUrl(urls.groupMemberAdd, activeConversationId), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrf,
      },
      body: JSON.stringify({ memberId }),
    });

    const payload = await readJsonSafe(response);
    if (!response.ok) {
      setMembersError(payload?.error || 'Impossible d\'ajouter ce membre.');
      return;
    }

    await loadGroupMembers();
    await loadConversations(true);
  };

  const removeMemberFromActiveGroup = async (memberId) => {
    if (!activeConversationId || !memberId || membersLoading) {
      return;
    }

    setMembersError('');
    const response = await apiFetch(resolveUrl(urls.groupMemberRemove, activeConversationId), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrf,
      },
      body: JSON.stringify({ memberId }),
    });

    const payload = await readJsonSafe(response);
    if (!response.ok) {
      setMembersError(payload?.error || 'Impossible de retirer ce membre.');
      return;
    }

    await loadGroupMembers();
    await loadConversations(true);
  };

  const renderSelectedParents = () => {
    if (!selectedParentsEl) {
      return;
    }

    selectedParentsEl.innerHTML = '';
    if (selectedParents.size === 0) {
      selectedParentsEl.innerHTML = '<div class="chat-inline-hint">Aucun parent selectionne.</div>';
      return;
    }

    Array.from(selectedParents.values()).forEach((parent) => {
      const chip = document.createElement('div');
      chip.className = 'chat-selected-chip';
      chip.innerHTML = `
        <span>${escapeHtml(parent.name)}</span>
        <button type="button" data-remove-parent="${parent.id}" aria-label="Retirer">x</button>
      `;
      selectedParentsEl.appendChild(chip);
    });
  };

  const renderParentSearchResults = (results, query) => {
    if (!parentResultsEl) {
      return;
    }

    parentResultsEl.innerHTML = '';

    if (!query || query.length < 2) {
      parentResultsEl.innerHTML = '<div class="chat-inline-hint">Entrez au moins 2 caracteres.</div>';
      return;
    }

    if (!results.length) {
      parentResultsEl.innerHTML = '<div class="chat-inline-hint">Aucun parent trouve.</div>';
      return;
    }

    results.forEach((parent) => {
      const isSelected = selectedParents.has(parent.id);
      const actionLabel = createMode === 'private' ? 'Discuter' : (isSelected ? 'Retirer' : 'Ajouter');

      const node = document.createElement('div');
      node.className = 'chat-parent-item';
      node.innerHTML = `
        <div class="chat-parent-meta">
          <div class="chat-parent-name">${escapeHtml(parent.name || parent.email || 'Parent')}</div>
          <div class="chat-parent-email">${escapeHtml(parent.email || '')}</div>
        </div>
        <button class="chat-parent-select-btn ${isSelected && createMode === 'group' ? 'is-selected' : ''}" type="button" data-parent-id="${parent.id}">
          ${actionLabel}
        </button>
      `;
      parentResultsEl.appendChild(node);
    });
  };

  const updateCreateModeUI = () => {
    const isPrivate = createMode === 'private';

    if (modePrivateBtn) modePrivateBtn.classList.toggle('is-active', isPrivate);
    if (modeGroupBtn) modeGroupBtn.classList.toggle('is-active', !isPrivate);
    if (selectedSection) selectedSection.hidden = isPrivate;
    if (groupNameWrap) groupNameWrap.hidden = isPrivate;
    if (createConfirmBtn) createConfirmBtn.hidden = isPrivate;
    if (createHintEl) {
      createHintEl.textContent = isPrivate
        ? 'Cliquez sur un parent pour creer une conversation privee.'
        : 'Selectionnez plusieurs parents, ajoutez un nom de groupe, puis creez.';
    }

    if (isPrivate) {
      selectedParents.clear();
      renderSelectedParents();
    }
  };

  const resetCreateModalState = () => {
    createMode = 'private';
    selectedParents.clear();
    parentSearchResults = [];
    if (parentSearchInput) parentSearchInput.value = '';
    if (groupNameInput) groupNameInput.value = '';
    setCreateError('');
    renderSelectedParents();
    renderParentSearchResults([], '');
    updateCreateModeUI();
  };

  const openCreateModal = () => {
    if (!createModal) {
      return;
    }

    resetCreateModalState();
    createModal.hidden = false;
    if (parentSearchInput) {
      parentSearchInput.focus();
    }
  };

  const closeCreateModal = () => {
    if (createModal) {
      createModal.hidden = true;
    }
    setCreateError('');
  };

  const redirectToConversation = (conversationId, redirectUrl = null) => {
    if (redirectUrl) {
      window.location.href = redirectUrl;
      return;
    }

    window.location.href = resolveUrl(urls.chatShow, conversationId);
  };

  const createPrivateConversation = async (parentId) => {
    if (creatingConversation) {
      return;
    }

    creatingConversation = true;
    setCreateError('');

    try {
      const response = await apiFetch(urls.createPrivate, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrf,
        },
        body: JSON.stringify({ parentId }),
      });

      const data = await readJsonSafe(response);
      if (!response.ok || !data || !data.id) {
        setCreateError((data && data.error) ? data.error : 'Impossible de creer la conversation privee.');
        return;
      }

      redirectToConversation(data.id, data.redirectUrl || null);
    } catch {
      setCreateError('Une erreur est survenue. Veuillez reessayer.');
    } finally {
      creatingConversation = false;
    }
  };

  const createGroupConversation = async () => {
    if (creatingConversation) {
      return;
    }

    if (!groupNameInput) {
      return;
    }

    if (selectedParents.size < 2) {
      setCreateError('Selectionnez au moins 2 autres parents pour creer un groupe.');
      return;
    }

    const title = groupNameInput.value.trim();
    if (!title) {
      setCreateError('Le nom du groupe est obligatoire.');
      return;
    }

    creatingConversation = true;
    if (createConfirmBtn) createConfirmBtn.disabled = true;

    try {
      const response = await apiFetch(urls.createGroup, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrf,
        },
        body: JSON.stringify({
          title,
          memberIds: Array.from(selectedParents.keys()),
        }),
      });

      const data = await readJsonSafe(response);
      if (!response.ok || !data || !data.id) {
        setCreateError((data && data.error) ? data.error : 'Impossible de creer le groupe.');
        return;
      }

      redirectToConversation(data.id, data.redirectUrl || null);
    } catch {
      setCreateError('Une erreur est survenue. Veuillez reessayer.');
    } finally {
      creatingConversation = false;
      if (createConfirmBtn) createConfirmBtn.disabled = false;
    }
  };

  const searchParents = async () => {
    if (!urls.parentSearch || !parentSearchInput) {
      return;
    }

    const query = parentSearchInput.value.trim();
    if (query.length < 2) {
      parentSearchResults = [];
      renderParentSearchResults(parentSearchResults, query);
      return;
    }

    const response = await fetch(`${urls.parentSearch}?q=${encodeURIComponent(query)}`);
    const data = await readJsonSafe(response);
    parentSearchResults = Array.isArray(data) ? data : [];
    renderParentSearchResults(parentSearchResults, query);
  };

  const leaveActiveGroup = async () => {
    if (!activeConversationId) {
      return;
    }

    stopLocalTyping({ conversationId: activeConversationId, force: true });

    const response = await apiFetch(resolveUrl(urls.groupLeave, activeConversationId), {
      method: 'POST',
    });
    const data = await readJsonSafe(response);

    if (!response.ok) {
      return;
    }

    if (data && data.redirectUrl) {
      window.location.href = data.redirectUrl;
      return;
    }

    window.location.href = '/chat';
  };

  const hideActiveConversation = async () => {
    if (!activeConversationId || !urls.conversationHide) {
      return;
    }

    const confirmed = window.confirm('Supprimer cette conversation pour vous uniquement ?');
    if (!confirmed) {
      return;
    }

    const conversationId = activeConversationId;
    stopLocalTyping({ conversationId, force: true });
    const response = await apiFetch(resolveUrl(urls.conversationHide, conversationId), {
      method: 'POST',
    });
    const payload = await readJsonSafe(response);

    if (!response.ok) {
      window.alert(payload?.error || 'Impossible de supprimer cette conversation pour vous.');
      return;
    }

    lastConversations = lastConversations.filter((item) => Number(item.id) !== Number(conversationId));
    renderConversations(lastConversations);
    clearActiveConversationSelection();
  };

  if (chatSearchEl) {
    chatSearchEl.addEventListener('input', () => {
      loadConversations(false);
    });
  }

  if (newConversationBtn) {
    newConversationBtn.addEventListener('click', openCreateModal);
  }

  if (createCloseBtn) {
    createCloseBtn.addEventListener('click', closeCreateModal);
  }

  if (createCancelBtn) {
    createCancelBtn.addEventListener('click', closeCreateModal);
  }

  if (createConfirmBtn) {
    createConfirmBtn.addEventListener('click', createGroupConversation);
  }

  if (modePrivateBtn) {
    modePrivateBtn.addEventListener('click', () => {
      createMode = 'private';
      setCreateError('');
      updateCreateModeUI();
      renderParentSearchResults(parentSearchResults, (parentSearchInput?.value || '').trim());
    });
  }

  if (modeGroupBtn) {
    modeGroupBtn.addEventListener('click', () => {
      createMode = 'group';
      setCreateError('');
      updateCreateModeUI();
      renderParentSearchResults(parentSearchResults, (parentSearchInput?.value || '').trim());
    });
  }

  if (createModal) {
    createModal.addEventListener('click', (event) => {
      const target = event.target;
      if (target instanceof HTMLElement && target.classList.contains('chat-modal-backdrop')) {
        closeCreateModal();
      }
    });
  }

  if (parentSearchInput) {
    parentSearchInput.addEventListener('input', () => {
      setCreateError('');
      if (searchTimer) {
        clearTimeout(searchTimer);
      }

      searchTimer = setTimeout(() => {
        searchParents();
      }, 250);
    });
  }

  if (parentResultsEl) {
    parentResultsEl.addEventListener('click', (event) => {
      const target = event.target;
      if (!(target instanceof HTMLElement)) {
        return;
      }

      const button = target.closest('[data-parent-id]');
      if (!button) {
        return;
      }

      const parentId = Number(button.getAttribute('data-parent-id'));
      if (!parentId) {
        return;
      }

      setCreateError('');

      if (createMode === 'private') {
        createPrivateConversation(parentId);
        return;
      }

      const parent = parentSearchResults.find((item) => Number(item.id) === parentId);
      if (!parent) {
        return;
      }

      if (selectedParents.has(parent.id)) {
        selectedParents.delete(parent.id);
      } else {
        selectedParents.set(parent.id, parent);
      }

      renderSelectedParents();
      renderParentSearchResults(parentSearchResults, (parentSearchInput.value || '').trim());
    });
  }

  if (selectedParentsEl) {
    selectedParentsEl.addEventListener('click', (event) => {
      const target = event.target;
      if (!(target instanceof HTMLElement)) {
        return;
      }

      const removeId = Number(target.getAttribute('data-remove-parent'));
      if (!removeId) {
        return;
      }

      selectedParents.delete(removeId);
      renderSelectedParents();
      renderParentSearchResults(parentSearchResults, (parentSearchInput?.value || '').trim());
      setCreateError('');
    });
  }

  if (groupNameInput) {
    groupNameInput.addEventListener('input', () => setCreateError(''));
    groupNameInput.addEventListener('keydown', (event) => {
      if (event.key === 'Enter') {
        event.preventDefault();
        createGroupConversation();
      }
    });
  }

  if (menuBtn && menuEl) {
    menuBtn.addEventListener('click', (event) => {
      event.stopPropagation();
      menuEl.classList.toggle('open');
    });

    menuEl.addEventListener('click', async (event) => {
      const target = event.target;
      if (!(target instanceof HTMLElement)) {
        return;
      }

      const action = target.dataset.action;
      menuEl.classList.remove('open');

      if (!action || !activeConversationId) {
        return;
      }

      if (action === 'hide-conversation') {
        await hideActiveConversation();
        return;
      }

      if (action === 'leave-group') {
        await leaveActiveGroup();
      }

      if (action === 'manage-members') {
        await openMembersModal();
      }

      if (action === 'images') {
        await openImagesModal();
      }
    });
  }

  if (chatMessagesEl) {
    chatMessagesEl.addEventListener('click', (event) => {
      const target = event.target;
      if (!(target instanceof HTMLElement)) {
        return;
      }

      const summarizeButton = target.closest('.js-summarize-attachment');
      if (summarizeButton instanceof HTMLButtonElement) {
        event.preventDefault();
        event.stopPropagation();
        void handleAttachmentSummaryToggle(summarizeButton);
        return;
      }

      const translateButton = target.closest('.js-translate-message');
      if (translateButton instanceof HTMLButtonElement) {
        event.preventDefault();
        event.stopPropagation();
        void handleMessageTranslationToggle(translateButton);
      }
    });
  }

  if (chatSendBtn) {
    chatSendBtn.addEventListener('click', sendMessage);
  }

  if (chatVoiceToggleBtn) {
    chatVoiceToggleBtn.addEventListener('pointerdown', () => {
      if (chatVoiceToggleBtn.disabled) {
        return;
      }

      voiceLongPressTriggered = false;
      if (voiceLongPressTimer) {
        clearTimeout(voiceLongPressTimer);
      }

      voiceLongPressTimer = setTimeout(() => {
        voiceLongPressTriggered = true;
        openVoiceRecorder();
      }, 320);
    });

    const clearVoiceLongPress = () => {
      if (voiceLongPressTimer) {
        clearTimeout(voiceLongPressTimer);
        voiceLongPressTimer = null;
      }
    };

    chatVoiceToggleBtn.addEventListener('pointerup', () => {
      clearVoiceLongPress();
      if (voiceLongPressTriggered && voiceRecorderState === 'recording') {
        stopVoiceRecording();
      }
    });
    chatVoiceToggleBtn.addEventListener('pointerleave', clearVoiceLongPress);
    chatVoiceToggleBtn.addEventListener('pointercancel', clearVoiceLongPress);

    chatVoiceToggleBtn.addEventListener('click', () => {
      if (voiceLongPressTriggered) {
        voiceLongPressTriggered = false;
        return;
      }

      if (!chatVoiceRecorder) {
        return;
      }

      if (chatVoiceRecorder.hidden) {
        openVoiceRecorder();
      } else {
        closeVoiceRecorder();
      }
    });
  }

  if (chatVoiceStartBtn) {
    chatVoiceStartBtn.addEventListener('click', () => {
      openVoiceRecorder();
      void startVoiceRecording();
    });
  }

  if (chatVoiceStopBtn) {
    chatVoiceStopBtn.addEventListener('click', () => {
      stopVoiceRecording();
    });
  }

  if (chatVoicePreviewPlayBtn) {
    chatVoicePreviewPlayBtn.addEventListener('click', () => {
      void toggleVoicePreviewPlayback();
    });
  }

  if (chatVoiceSendBtn) {
    chatVoiceSendBtn.addEventListener('click', () => {
      void sendVoiceMessage();
    });
  }

  if (chatVoiceCancelBtn) {
    chatVoiceCancelBtn.addEventListener('click', () => {
      closeVoiceRecorder();
      setUploadStatus('');
      setVoiceStatus('Enregistrement annule.');
    });
  }

  if (chatVoicePreviewAudio) {
    chatVoicePreviewAudio.addEventListener('ended', () => {
      updatePreviewPlayButton(false);
    });

    chatVoicePreviewAudio.addEventListener('pause', () => {
      updatePreviewPlayButton(false);
    });

    chatVoicePreviewAudio.addEventListener('play', () => {
      updatePreviewPlayButton(true);
    });
  }

  if (chatInputEl) {
    chatInputEl.addEventListener('input', () => {
      handleLocalTypingInput();
    });

    chatInputEl.addEventListener('keydown', (event) => {
      if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        sendMessage();
      }
    });
  }

  if (chatEmojiBtn && chatEmojiPicker) {
    chatEmojiBtn.addEventListener('click', () => {
      chatEmojiPicker.hidden = !chatEmojiPicker.hidden;
    });

    chatEmojiPicker.addEventListener('click', (event) => {
      const target = event.target;
      if (!(target instanceof HTMLElement) || target.tagName !== 'BUTTON' || !chatInputEl) {
        return;
      }

      chatInputEl.value += target.textContent || '';
      chatInputEl.focus();
      handleLocalTypingInput();
    });
  }

  if (chatFileInput && chatUploadPreview) {
    chatFileInput.addEventListener('change', () => {
      setUploadStatus('');
      const files = Array.from(chatFileInput.files || []);
      if (files.length === 0) {
        chatUploadPreview.hidden = true;
        chatUploadPreview.innerHTML = '';
        return;
      }

      const items = files.map((file) => `
        <div class="chat-upload-item">
          <span class="chat-upload-item-name">${escapeHtml(file.name)}</span>
          <span class="chat-upload-item-size">${formatFileSize(file.size)}</span>
        </div>
      `).join('');

      chatUploadPreview.hidden = false;
      chatUploadPreview.innerHTML = `
        <div class="chat-upload-summary">${files.length} fichier${files.length > 1 ? 's' : ''} selectionne${files.length > 1 ? 's' : ''}</div>
        <div class="chat-upload-list">${items}</div>
      `;
    });
  }

  if (editCancelBtn && editBanner && chatInputEl) {
    editCancelBtn.addEventListener('click', () => {
      editingMessageId = null;
      editBanner.hidden = true;
      chatInputEl.value = '';
      stopLocalTyping({ force: true });
    });
  }

  if (imagesCloseBtn) {
    imagesCloseBtn.addEventListener('click', closeImagesModal);
  }

  if (imagesModal) {
    imagesModal.addEventListener('click', (event) => {
      const target = event.target;
      if (target instanceof HTMLElement && target.classList.contains('chat-modal-backdrop')) {
        closeImagesModal();
      }
    });
  }

  if (membersCloseBtn) {
    membersCloseBtn.addEventListener('click', closeMembersModal);
  }

  if (membersModal) {
    membersModal.addEventListener('click', (event) => {
      const target = event.target;
      if (target instanceof HTMLElement && target.classList.contains('chat-modal-backdrop')) {
        closeMembersModal();
      }
    });
  }

  if (membersSearchInput) {
    membersSearchInput.addEventListener('input', () => {
      setMembersError('');

      if (membersSearchTimer) {
        clearTimeout(membersSearchTimer);
      }

      membersSearchTimer = setTimeout(() => {
        searchParentsForGroupMembers();
      }, 250);
    });
  }

  if (membersSearchResultsEl) {
    membersSearchResultsEl.addEventListener('click', (event) => {
      const target = event.target;
      if (!(target instanceof HTMLElement)) {
        return;
      }

      const button = target.closest('[data-member-add-id]');
      if (!button) {
        return;
      }

      const memberId = Number(button.getAttribute('data-member-add-id'));
      if (!memberId) {
        return;
      }

      addMemberToActiveGroup(memberId);
    });
  }

  if (membersListEl) {
    membersListEl.addEventListener('click', (event) => {
      const target = event.target;
      if (!(target instanceof HTMLElement)) {
        return;
      }

      const button = target.closest('[data-member-remove-id]');
      if (!button) {
        return;
      }

      const memberId = Number(button.getAttribute('data-member-remove-id'));
      if (!memberId) {
        return;
      }

      removeMemberFromActiveGroup(memberId);
    });
  }

  document.addEventListener('click', () => {
    if (menuEl) {
      menuEl.classList.remove('open');
    }
    document.querySelectorAll('.chat-message-actions').forEach((node) => node.classList.remove('open'));
  });

  document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') {
      return;
    }

    if (createModal && !createModal.hidden) {
      closeCreateModal();
    }

    if (imagesModal && !imagesModal.hidden) {
      closeImagesModal();
    }

    if (membersModal && !membersModal.hidden) {
      closeMembersModal();
    }

    if (chatVoiceRecorder && !chatVoiceRecorder.hidden) {
      closeVoiceRecorder();
    }
  });

  window.addEventListener('pagehide', () => {
    if (voiceLongPressTimer) {
      clearTimeout(voiceLongPressTimer);
      voiceLongPressTimer = null;
    }
    stopLocalTyping({ force: true, keepalive: true });
    resetVoiceRecorder({ hidePanel: true });
  });

  window.addEventListener('beforeunload', () => {
    if (voiceLongPressTimer) {
      clearTimeout(voiceLongPressTimer);
      voiceLongPressTimer = null;
    }
    stopLocalTyping({ force: true, keepalive: true });
    resetVoiceRecorder({ hidePanel: true });
  });

  const handleRealtimePayload = async (payload) => {
    if (!payload || !payload.type) {
      return;
    }

    if (payload.type === 'conversation.typing') {
      upsertRemoteTyping(payload);
      return;
    }

    if (payload.type.startsWith('message.')) {
      if (payload.type === 'message.created') {
        removeRemoteTypingUser(payload.conversationId, payload?.message?.senderId);
        if (Number(payload.conversationId) === Number(activeConversationId)) {
          renderTypingIndicators();
        }

        if (activeConversationId && Number(payload.conversationId) === Number(activeConversationId)) {
          addMessageToDom(payload.message);
          if (chatMessagesEl) chatMessagesEl.scrollTop = chatMessagesEl.scrollHeight;
        }

        if (!updateConversationActivity(payload.conversationId, payload.message)) {
          const upserted = await fetchAndUpsertConversation(payload.conversationId);
          if (!upserted) {
            await loadConversations(true);
          }
        }

        return;
      }

      if (activeConversationId && Number(payload.conversationId) === Number(activeConversationId)) {
        updateMessageDom(payload.message);
      }
      await loadConversations(true);
    }

    if (payload.type === 'conversation.updated') {
      await loadConversations(true);
      if (membersModal && !membersModal.hidden && Number(payload.conversationId) === Number(activeConversationId)) {
        await loadGroupMembers();
      }
    }
  };

  const connectMercure = () => {
    if (!mercureUrl) {
      return;
    }

    const eventSource = new EventSource(mercureUrl);
    eventSource.onmessage = async (event) => {
      let payload = null;
      try {
        payload = JSON.parse(event.data);
      } catch {
        return;
      }

      await handleRealtimePayload(payload);
    };
  };

  const connectWebSocket = () => {
    if (!websocketUrl) {
      connectMercure();
      return;
    }

    const separator = websocketUrl.includes('?') ? '&' : '?';
    const urlWithToken = websocketToken
      ? `${websocketUrl}${separator}token=${encodeURIComponent(websocketToken)}`
      : websocketUrl;

    let socket = null;
    try {
      socket = new WebSocket(urlWithToken);
    } catch {
      connectMercure();
      return;
    }

    let opened = false;
    socket.onopen = () => {
      opened = true;
    };

    socket.onmessage = async (event) => {
      let payload = null;
      try {
        payload = JSON.parse(event.data);
      } catch {
        return;
      }

      await handleRealtimePayload(payload);
    };

    socket.onclose = () => {
      if (!opened) {
        connectMercure();
        return;
      }

      setTimeout(() => {
        connectWebSocket();
      }, 1500);
    };

    socket.onerror = () => {
      if (!opened && socket) {
        socket.close();
      }
    };
  };

  connectWebSocket();

  resetVoiceRecorder({ hidePanel: true, stopRecorder: false });
  setInputEnabled(false);
  updateConversationHeaderActions();
  loadConversations(true);
})();
