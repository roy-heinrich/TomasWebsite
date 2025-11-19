<?php
// 🚨 SECURITY: Load secure chatbot configuration
require_once __DIR__ . '/chatbot_config.php';

$docRoot = str_replace('\\','/', realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
$dir = str_replace('\\','/', realpath(__DIR__));
$widget_base_url = '';
if ($docRoot && strpos($dir, $docRoot) === 0) {
    $widget_base_url = substr($dir, strlen($docRoot));
}
// Ensure leading slash
if ($widget_base_url === '') {
    $widget_base_url = '';
} else if ($widget_base_url[0] !== '/') {
    $widget_base_url = '/' . $widget_base_url;
}
// final URL for the logo
$widget_logo = rtrim($widget_base_url, '/') . '/PHbot1.png';
$widget_logo1 = rtrim($widget_base_url, '/') . '/PHbot2.png';
?>
<!-- Chatbot Widget - Enhanced with fixes from complete_test_gui.html - VERSION: 2024-01-21-PRODUCTION -->
<!-- Inject configured chatbot endpoints into JS (populated from environment via chatbot_config.php) -->
<script>
    // Use server-calculated widget base URL to build proxy paths reliably
    // proxy.php lives inside the same TomasChatBot directory, so append only /proxy.php
    const PROXY_CHAT_URL = '<?php echo addslashes(rtrim($widget_base_url, "/")); ?>/proxy.php?action=chat';
    const PROXY_CLEAR_URL = '<?php echo addslashes(rtrim($widget_base_url, "/")); ?>/proxy.php?action=clear';
    const PROXY_HEALTH_URL = '<?php echo addslashes(rtrim($widget_base_url, "/")); ?>/proxy.php?action=health';
    function isChatConfigured() { return true; /* server-side will report configuration via proxy errors if not configured */ }
</script>
<style>
#chatbotWidgetBtn {
    position: fixed;
    bottom: 30px;
    right: 30px;
    z-index: 9999;
    background: linear-gradient(135deg, #1d3383be, #7135adc0);
    color: #fff;
    border: none;
    border-radius: 50%;
    width: 60px; height: 60px;
    box-shadow: 0 4px 16px rgba(0,0,0,0.18);
    cursor: pointer;
    font-size: 2rem;
    display: flex; align-items: center; justify-content: center;
    overflow: hidden;
    padding: 0;
}
#chatbotWidgetBtn img {
    width: 80%;
    height: 80%;
    object-fit: contain;
}
#chatbotWidgetContainer {
    display: none;
    position: fixed;
    bottom: 100px;
    right: 30px;
    z-index: 10000;
    width: 370px;
    max-width: 95vw;
    height: 520px;
    background: white;
    border-radius: 18px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.18);
    overflow: hidden;
    flex-direction: column;
}
@media (max-width: 500px) {
    #chatbotWidgetContainer { width: 98vw; right: 1vw; }
}

@media (max-width: 500px) {
    #chatbotWidgetBtn {
        width: 48px;
        height: 48px;
        bottom: 18px;
        right: 12px;
    }
    #chatbotWidgetBtn img {
        width: 70%;
        height: 75%;
    }
}
</style>
<style>
/* Disclaimer modal styles */
.tomas-modal-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,0.45); z-index: 11000; }
.tomas-modal { position: fixed; right: 20px; bottom: 120px; width: 360px; max-width: calc(95vw - 40px); background: #fff; border-radius: 12px; box-shadow: 0 12px 40px rgba(0,0,0,0.25); z-index: 11001; overflow: hidden; font-family: system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial; }
.tomas-modal-header { display:flex; align-items:center; gap:10px; padding:12px 14px; background: linear-gradient(135deg,#667eea,#764ba2); color: #fff; }
.tomas-modal-header h3 { margin:0; font-size:1rem; }
.tomas-modal-body { padding:12px 14px; color:#222; font-size:0.95rem; }
.tomas-modal-footer { display:flex; justify-content:flex-end; gap:8px; padding:10px 14px; background:#fafafa; }
.tomas-btn { padding:8px 12px; border-radius:8px; border: none; cursor:pointer; font-weight:600; }
.tomas-btn-primary { background: #4f46e5; color:#fff; }
.tomas-btn-secondary { background: #eef2ff; color:#1f2937; }
.tomas-modal-close { background:none; border:none; color:rgba(255,255,255,0.9); font-size:1.1rem; margin-left:auto; cursor:pointer; }
body.tomas-modal-open { overflow: hidden; }

@media (max-width: 520px) {
    .tomas-modal { left: 6px; right: 6px; bottom: 70px; top: auto; width: auto; max-width: calc(100vw - 12px); border-radius: 10px; max-height: calc(100vh - 140px); overflow:auto; }
    .tomas-modal-header { padding: 10px; }
    .tomas-modal-body { padding: 10px; font-size: 0.95rem; }
    .tomas-modal-footer { padding: 8px; }
}
</style>


<style>
/* Toast container */
#tomasToast {
    position: fixed;
    right: 20px;
    bottom: 95px;
    z-index: 12000;
    max-width: 320px;
    display: none;
    flex-direction: column;
    align-items: flex-end;
    gap: 8px;
}

/* Individual toast */
.tomas-toast {
    background: linear-gradient(135deg, #6d5dfc 0%, #5a4fcf 100%);
    color: white;
    padding: 12px 16px;
    border-radius: 12px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
    font-family: 'Nunito', 'Segoe UI', system-ui, -apple-system, sans-serif;
    font-size: 13px;
    font-weight: 500;
    line-height: 1.4;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    transform: translateX(100px);
    opacity: 0;
    animation: toastSlideIn 0.4s ease-out forwards;
    position: relative;
    overflow: hidden;
}

.tomas-toast::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 2px;
    background: rgba(255, 255, 255, 0.3);
    animation: toastProgress 4s linear forwards;
}

/* Disabled state while waiting for bot response */
.disabled-for-response { opacity: 0.6; pointer-events: none; }
.tomas-disabled { opacity: 0.6; pointer-events: none; }

@keyframes toastSlideIn {
    0% {
        transform: translateX(100px);
        opacity: 0;
    }
    100% {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes toastSlideOut {
    0% {
        transform: translateX(0);
        opacity: 1;
    }
    100% {
        transform: translateX(100px);
        opacity: 0;
    }
}

@keyframes toastProgress {
    0% {
        width: 100%;
    }
    100% {
        width: 0%;
    }
}

/* Responsive design */
@media (max-width: 768px) {
    #tomasToast {
        right: 10px;
        bottom: 80px;
        max-width: 280px;
    }
    
    .tomas-toast {
        font-size: 12px;
        padding: 10px 14px;
    }
}
</style>

<!-- Toast container -->
<div id="tomasToast"></div>

<script>
function showToast(message, duration = 4000) {
    const container = document.getElementById('tomasToast');
    if (!container) return;
    
    const toast = document.createElement('div');
    toast.className = 'tomas-toast';
    toast.textContent = message;
    
    container.appendChild(toast);
    container.style.display = 'flex';
    
    // Remove toast after duration
    setTimeout(() => {
        toast.style.animation = 'toastSlideOut 0.4s ease-in forwards';
        setTimeout(() => {
            toast.remove();
            if (!container.childElementCount) {
                container.style.display = 'none';
            }
        }, 400);
    }, duration);
}
</script>



<button id="chatbotWidgetBtn" title="Chat with us">
    <img src="<?php echo htmlspecialchars($widget_logo1, ENT_QUOTES); ?>" alt="Chat with us" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
    <div style="display: none; width: 100%; height: 100%; align-items: center; justify-content: center; font-size: 1.5rem;">💬</div>
</button>
<div id="chatbotWidgetContainer">
    <!-- ...existing code from inside .chat-container in chatbot.php... -->
    <div class="chat-header">
        <h1 style="font-size:1.2em; display: flex; align-items: center;">
            <img src="<?php echo htmlspecialchars($widget_logo, ENT_QUOTES); ?>" alt="Tomas Logo" style="width: 45px; height: 45px; margin-right: 10px;" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
            <div style="display: none; width: 45px; height: 45px; margin-right: 10px; align-items: center; justify-content: center; background: rgba(255,255,255,0.2); border-radius: 50%; font-size: 1.5rem;">🤖</div>
            TOMAS
        </h1>
        <button onclick="closeChatbotWidget()" style="position:absolute;top:10px;right:15px;background:none;border:none;font-size:1.2em;color:#fff;cursor:pointer;">&times;</button>
        <p style="font-size:0.80em;">Ask me anything about our school!</p>
    </div>
    <div class="chat-messages" id="chatMessages">
        <div class="welcomee-message">
            <p>👋 Hello! I'm Tomas. I'm here to help you with your questions. Feel free to ask me anything!</p>
        </div>
    </div>
<div class="chat-input-container">
    <div class="status-indicator" id="statusIndicator"></div>
    <div class="chat-input">
        <textarea id="messageInput" placeholder="Type your message here..." onkeypress="handleKeyPress(event)" oninput="autoResize(this)" rows="1"></textarea>
        <button class="send-btn" onclick="sendMessage()">
            <svg viewBox="0 0 24 24" fill="currentColor" width="20" height="20">
                <path d="M2 21l21-9L2 3v7l15 2-15 2v7z"/>
            </svg>
        </button>
    </div>
</div>
    <div class="powered-by" style="font-size:0.8em;">Powered by AI • Connected to live support</div>
</div>

<!-- Disclaimer Modal -->
<div id="tomasDisclaimerModal" aria-hidden="true" style="display:none;">
    <div class="tomas-modal-backdrop"></div>
    <div class="tomas-modal">
        <div class="tomas-modal-header">
            <img src="<?php echo htmlspecialchars($widget_logo, ENT_QUOTES); ?>" alt="Tomas" style="width:40px;height:40px;margin-right:10px;">
            <div>
                <h3 style="margin:0;font-size:1.05rem;">Chatbot Terms &amp; Privacy</h3>
                <small style="opacity:0.85;">Please read and accept to continue</small>
            </div>
            <button class="tomas-modal-close" title="Close">&times;</button>
        </div>
        <div class="tomas-modal-body">
            <p style="margin-top:6px;">Before using Tomas, please review and accept the following:</p>
            <ul style="margin-left:1rem;color:#333;">
                <li>The chatbot provides automated assistance and recommended answers only.</li>
                <li>Your conversation is stored locally in your browser for convenience and deleted after <strong>7 hours</strong> of inactivity.</li>
                <li>No chat content is stored in the server database. You remain anonymous unless you choose to introduce yourself.</li>
                <li>If you provide your name, it may be included in the chat history locally; that is the only personal data kept.</li>
            </ul>
            <p style="margin-top:6px;color:#555;">The disclaimer will reappear after a reset or when the chat history expires.</p>
        </div>
        <div class="tomas-modal-footer">
            <button id="tomasCancelBtn" class="tomas-btn tomas-btn-secondary">Cancel</button>
            <button id="tomasAgreeBtn" class="tomas-btn tomas-btn-primary">I Agree</button>
        </div>
    </div>
</div>
<script>
let isWaitingForResponse = false;

// Health check throttle to avoid calling health endpoint on every message
let lastHealthCheckedAt = 0;
const HEALTH_CHECK_INTERVAL = 30 * 1000; // 30 seconds

// Local storage configuration
const STORAGE_KEY = 'tomas_chat_history_v1';
const TTL_MS = 7 * 60 * 60 * 1000; // 7 hours


function escapeHtml(unsafe) {
    return unsafe
         .replace(/&/g, "&amp;")
         .replace(/</g, "&lt;")
         .replace(/>/g, "&gt;")
         .replace(/"/g, "&quot;")
         .replace(/'/g, "&#039;");
}

// 🚨 ENHANCED: Better HTML sanitization for user input
function sanitizeUserInput(input) {
    if (!input) return '';
    
    // First escape HTML entities
    let sanitized = escapeHtml(input);
    
    // Additional sanitization for common attack patterns
    sanitized = sanitized
        .replace(/javascript:/gi, '')
        .replace(/on\w+\s*=/gi, '')
        .replace(/<script/gi, '&lt;script')
        .replace(/<\/script>/gi, '&lt;/script&gt;');
    
    return sanitized;
}

function getCurrentTime() {
    return new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

function showEntityFeedback(entities) {
    // 🆕 NEW: Show subtle feedback when important entities are recognized
    const recognizedTypes = {
        'person_name': '👤 Name',
        'child_name': '👶 Child',
        'grade_level': '📚 Grade',
        'age': '🎂 Age',
        'phone_number': '📞 Phone',
        'email': '📧 Email',
        'academic_subject': '📖 Subject'
    };
    
    const recognizedItems = entities
        .filter(e => recognizedTypes[e.entity_type])
        .map(e => recognizedTypes[e.entity_type])
        .slice(0, 3); // Show max 3 to avoid clutter
    
    if (recognizedItems.length > 0) {
        showStatus(`Recognized: ${recognizedItems.join(', ')}`, 'success');
    }
}

// 🚨 ENHANCED: Better message formatting for conversational responses
function formatConversationalMessage(message) {
    if (!message) return '';
    
    // Handle HTML content (like Messenger buttons) without escaping
    if (message.includes('<a href=') || message.includes('<button')) {
        return message;
    }
    
    // For regular text, ensure proper formatting
    return message
        .replace(/\n\n/g, '<br><br>')  // Double line breaks
        .replace(/\n/g, '<br>')        // Single line breaks
        .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')  // Bold text
        .replace(/\*(.*?)\*/g, '<em>$1</em>');             // Italic text
}


function showStatus(message, type = 'success') {
    const indicator = document.getElementById('statusIndicator');
    indicator.textContent = message;
    indicator.className = `status-indicator ${type}`;
    setTimeout(() => { indicator.className = 'status-indicator'; }, 3000);
}

function showTypingIndicator() {
    if (document.getElementById('typingIndicator')) return;
    const chatMessages = document.getElementById('chatMessages');
    const typingDiv = document.createElement('div');
    typingDiv.className = 'typing-indicator active';
    typingDiv.id = 'typingIndicator';
    typingDiv.innerHTML = `<div class="typing-dots">
        <div class="typing-dot"></div>
        <div class="typing-dot"></div>
        <div class="typing-dot"></div>
    </div>`;
    chatMessages.appendChild(typingDiv);
    chatMessages.scrollTop = chatMessages.scrollHeight;
}

function hideTypingIndicator() {
    const typingIndicator = document.getElementById('typingIndicator');
    if (typingIndicator) typingIndicator.remove();
}

// Utility: if older saved text accidentally contains the time at the end, strip it
function stripTrailingTime(text, time) {
    if (!text || !time) return text;
    // escape time for regex
    const escaped = time.replace(/[.*+?^${}()|[\]\\]/g,'\\$&');
    const regex = new RegExp('\\s*' + escaped + '\\s*$');
    return text.replace(regex, '').trim();
}

// create message DOM element (keeps message-content and message-time separate)
function createMessageElement(sender, text, time) {
    const div = document.createElement('div');
    div.className = `message ${sender}`;
    const content = document.createElement('div');
    content.className = 'message-content';
    
        // 🚨 FIX: Render HTML for bot messages, escape HTML for user messages
    if (sender === 'bot') {
        content.innerHTML = text || '';  // This renders HTML (like Messenger button)
    } else {
        // 🚨 ENHANCED: Better HTML escaping for user messages
        content.textContent = text || '';  // This escapes HTML for user messages
    }
    
    div.appendChild(content);
    if (time) {
        const timeEl = document.createElement('div');
        timeEl.className = 'message-time';
        timeEl.textContent = time;
        div.appendChild(timeEl);
    }
    // store original values so saveHistory can use them reliably
    div.dataset.text = text || '';
    div.dataset.time = time || '';
    return div;
}

function saveHistory() {
    try {
        const container = document.getElementById('chatMessages');
        const messages = [];
        container.querySelectorAll('.message').forEach(el => {
            // prefer dataset values (set when messages are created)
            const text = (el.dataset && el.dataset.text) ? el.dataset.text : (el.querySelector('.message-content') ? el.querySelector('.message-content').textContent : '');
            const time = (el.dataset && el.dataset.time) ? el.dataset.time : (el.querySelector('.message-time') ? el.querySelector('.message-time').textContent : '');
            const sender = el.classList.contains('user') ? 'user' : 'bot';
            messages.push({ sender, text, time });
        });
        const payload = { expires: Date.now() + TTL_MS, messages };
        localStorage.setItem(STORAGE_KEY, JSON.stringify(payload));
    } catch (e) {
        console.error('saveHistory error', e);
    }
}

function loadHistory() {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
            if (!raw) return;
            const obj = JSON.parse(raw);
            if (!obj || !obj.expires || Date.now() > obj.expires) {
                // history expired: remove both history and disclaimer acceptance so
                // the modal will reappear on next chat attempt
                localStorage.removeItem(STORAGE_KEY);
                try { localStorage.removeItem(DISCLAIMER_KEY); } catch(e){}
                return;
            }
        if (!Array.isArray(obj.messages) || obj.messages.length === 0) return;

        const container = document.getElementById('chatMessages');
        container.innerHTML = ''; // replace welcome with history

        obj.messages.forEach(m => {
            let text = m.text || '';
            const time = m.time || '';
            // Sanitize: older saved 'text' might already include time appended; strip if so
            if (time && text && text.endsWith(time)) {
                text = stripTrailingTime(text, time);
            }
            const msgEl = createMessageElement(m.sender, text, time);
            container.appendChild(msgEl);
        });

        hideTypingIndicator();
        container.scrollTop = container.scrollHeight;
    } catch (e) {
        console.error('loadHistory error', e);
        localStorage.removeItem(STORAGE_KEY);
    }
}

function addMessage(sender, text, showTime = true, isSplitMessage = false) {
    const chatMessages = document.getElementById('chatMessages');
    // Remove initial welcome if first real message is being added and welcome exists as sole child
    const welcome = chatMessages.querySelector('.welcomee-message');
    if (welcome && chatMessages.children.length === 1) {
        welcome.remove();
    }

    const time = showTime ? getCurrentTime() : '';
    const messageEl = createMessageElement(sender, text, time);
    
    // 📱 MESSAGE SPLITTING: Add split-message class for visual styling
    if (isSplitMessage && sender === 'bot') {
        messageEl.classList.add('split-message');
    }
    
    chatMessages.appendChild(messageEl);
    chatMessages.scrollTop = chatMessages.scrollHeight;

    // Persist after each message
    saveHistory();
}

function autoResize(textarea) {
    textarea.style.height = 'auto';
    const maxHeight = 120;
    const minHeight = 20;
    let newHeight = Math.max(minHeight, textarea.scrollHeight);
    if (newHeight > maxHeight) {
        newHeight = maxHeight;
        textarea.style.overflowY = 'auto';
    } else {
        textarea.style.overflowY = 'hidden';
    }
    textarea.style.height = newHeight + 'px';
}

// Disable/enable input and send buttons while awaiting bot response
function setInputDisabled(disabled) {
    try {
        const input = document.getElementById('messageInput');
        if (input) {
            input.disabled = !!disabled;
            if (disabled) input.classList.add('disabled-for-response'); else input.classList.remove('disabled-for-response');
        }
        const buttons = document.querySelectorAll('.send-btn');
        buttons.forEach(b => {
            b.disabled = !!disabled;
            if (disabled) {
                b.classList.add('tomas-disabled');
                b.setAttribute('aria-disabled', 'true');
            } else {
                b.classList.remove('tomas-disabled');
                b.removeAttribute('aria-disabled');
            }
        });
    } catch (e) {
        // ignore
    }
}

// Neutralize the old onclick handler safely. The new click behavior is handled further down
// to enforce the disclaimer flow.
const existingBtn = document.getElementById('chatbotWidgetBtn');
if (existingBtn) existingBtn.onclick = null;

function closeChatbotWidget() {
    document.getElementById('chatbotWidgetContainer').style.display = 'none';
}


// 🆕 NEW ADDITION: Clear conversation context when closing
function clearConversationContext() {
    // Send a signal to backend to clear conversation memory for this session
    try {
        const clearUrl = PROXY_CLEAR_URL;
        fetch(clearUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' }
        }).catch(error => console.log('Context clear failed:', error));
    } catch (error) {
        console.log('Context clear error:', error);
    }
}

// 🆕 NEW: Clear context when page is about to be unloaded (user navigating away)
// On page unload, only notify backend to clear ephemeral context; do NOT
// remove localStorage keys like disclaimer acceptance (so acceptance survives refresh/navigation).
window.addEventListener('beforeunload', function() {
    try {
        const clearUrl = PROXY_CLEAR_URL;
        fetch(clearUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' }
        }).catch(error => console.log('Context clear failed on unload:', error));
    } catch (error) {
        console.log('Context clear error on unload:', error);
    }
});

// ---------- Disclaimer modal behavior ----------
const DISCLAIMER_KEY = 'tomas_disclaimer_accepted_v1';
const DISCLAIMER_TIMESTAMP_KEY = 'tomas_chat_history_expires';

function showDisclaimerModal() {
    const modal = document.getElementById('tomasDisclaimerModal');
    if (!modal) return;
    modal.style.display = 'block';
    document.body.classList.add('tomas-modal-open');
}

function hideDisclaimerModal() {
    const modal = document.getElementById('tomasDisclaimerModal');
    if (!modal) return;
    modal.style.display = 'none';
    document.body.classList.remove('tomas-modal-open');
}

function userAcceptedDisclaimer() {
    // store acceptance in session only (so it reappears after a reset/close of browser session)
    try { localStorage.setItem(DISCLAIMER_KEY, '1'); } catch(e){}
    hideDisclaimerModal();
    // open widget when accepted
    document.getElementById('chatbotWidgetContainer').style.display = 'flex';
    setTimeout(() => document.getElementById('messageInput').focus(), 250);
}

function userCancelledDisclaimer() {
    try { localStorage.removeItem(DISCLAIMER_KEY); } catch(e){}
    hideDisclaimerModal();
    // keep widget closed and show a subtle message
    const container = document.getElementById('chatbotWidgetContainer');
    if (container) container.style.display = 'none';
        showToast('You declined the chatbot terms. You will not be able to use the chatbot until you accept.');
}

// Wire modal buttons and handle the chat widget button via event delegation
document.addEventListener('click', function(e){
    // Modal buttons
    if (e.target && e.target.id === 'tomasAgreeBtn') {
        userAcceptedDisclaimer();
        return;
    }
    if (e.target && e.target.id === 'tomasCancelBtn') {
        userCancelledDisclaimer();
        return;
    }
    if (e.target && e.target.classList && e.target.classList.contains('tomas-modal-close')) {
        hideDisclaimerModal();
        return;
    }

    // Chat button (or its inner img) clicked -> delegation ensures element exists
    const widgetBtnClicked = e.target.closest ? e.target.closest('#chatbotWidgetBtn') : (e.target && e.target.id === 'chatbotWidgetBtn');
    if (widgetBtnClicked) {
        // Prevent default behavior if any
        // Check acceptance and history expiry
        const raw = localStorage.getItem(STORAGE_KEY);
        if (raw) {
            try {
                const obj = JSON.parse(raw);
                if (!obj || !obj.expires || Date.now() > obj.expires) {
                    // expired - remove history and force disclaimer
                    localStorage.removeItem(STORAGE_KEY);
                        localStorage.removeItem(DISCLAIMER_KEY);
                }
            } catch(e) { localStorage.removeItem(STORAGE_KEY); sessionStorage.removeItem(DISCLAIMER_KEY); }
        }

        const isAcceptedNow = localStorage.getItem(DISCLAIMER_KEY) === '1';
        if (!isAcceptedNow) {
            showDisclaimerModal();
            return;
        }

        // If already accepted, toggle widget
        const container = document.getElementById('chatbotWidgetContainer');
        if (container.style.display === 'flex') container.style.display = 'none';
        else { container.style.display = 'flex'; setTimeout(()=>{ const mi = document.getElementById('messageInput'); if(mi) mi.focus(); }, 250); }
        return;
    }
});

// When clearing conversation context, also clear local storage history and disclaimer acceptance so it reappears
function clearAllLocalHistoryAndReset() {
    try { localStorage.removeItem(STORAGE_KEY); } catch(e){}
    try { localStorage.removeItem(DISCLAIMER_KEY); } catch(e){}
    try { localStorage.removeItem('tomas_user_session_id'); } catch(e){}
    clearConversationContext();
        // Show disclaimer immediately so user can re-accept before using
        showDisclaimerModal();
}

// Integrate with existing clearConversationContext used on beforeunload
const originalClearConversationContext = clearConversationContext;
clearConversationContext = function(){
    originalClearConversationContext();
    // also clear local history so modal returns after reset
    try { localStorage.removeItem(STORAGE_KEY); } catch(e){}
    // Do not remove disclaimer acceptance automatically here so users who
    // previously accepted don't get prompted on every context clear.
};

// The widget button click is handled by the delegated document click listener above.

// Ensure modal shows if history was reset by other flows (e.g. explicit reset button)
function forceDisclaimerOnReset() {
    try { localStorage.removeItem(DISCLAIMER_KEY); } catch(e){}
    showDisclaimerModal();
}

// Make clearAllLocalHistoryAndReset globally available
window.forceDisclaimerOnReset = forceDisclaimerOnReset;



// 🆕 NEW ADDITION: Get user timezone for personalized responses
function getUserTimezone() {
    try {
        return Intl.DateTimeFormat().resolvedOptions().timeZone;
    } catch (error) {
        console.log('Timezone detection failed:', error);
        return null;
    }
}

// 🆕 NEW ADDITION: Generate or retrieve user session ID for conversation memory
function getUserSessionId() {
    let sessionId = localStorage.getItem('tomas_user_session_id');
    if (!sessionId) {
        // Generate a unique session ID
        sessionId = 'user_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        localStorage.setItem('tomas_user_session_id', sessionId);
    }
    return sessionId;
}

// 🆕 NEW ADDITION: Get conversation history for context awareness
function getConversationHistory() {
    const chatMessages = document.getElementById('chatMessages');
    const history = [];
    
    // 🆕 Get last 6 messages for context (smaller payload to avoid backend timeouts)
    const messages = Array.from(chatMessages.querySelectorAll('.message')).slice(-6);
    
    messages.forEach(el => {
        // 🆕 Extract text from dataset or DOM content
        const text = (el.dataset && el.dataset.text) ? el.dataset.text : 
                    (el.querySelector('.message-content') ? el.querySelector('.message-content').textContent : '');
        const sender = el.classList.contains('user') ? 'user' : 'assistant';
        
        if (text.trim()) {
            // 🆕 Build conversation history in OpenAI format
            history.push({
                role: sender,
                content: text.trim()
            });
        }
    });
    
    return history;
}


async function sendMessage() {
    if (isWaitingForResponse) return;
    const input = document.getElementById('messageInput');
    const rawMessage = input.value.trim();
    if (!rawMessage) return;

    // 🚨 ENHANCED: Sanitize user input before processing
    const message = sanitizeUserInput(rawMessage);
    
    addMessage('user', message);
    input.value = '';
    autoResize(input);

    // Show typing indicator and send request
    showTypingIndicator();
    isWaitingForResponse = true;
    setInputDisabled(true);

    try {
        // 🆕 NEW: Get conversation history, timezone, and session ID for enhanced context
        const conversationHistory = getConversationHistory();
        const userTimezone = getUserTimezone();
        const sessionId = getUserSessionId();
        
        // Use local proxy endpoints (server will forward to configured backend)
        const apiUrl = PROXY_CHAT_URL;
        console.log('🌐 Connecting to chatbot API via proxy...');

        // 🆕 CORS Test: Rate-limited health check (avoid running every message)
        const now = Date.now();
        if (now - lastHealthCheckedAt > HEALTH_CHECK_INTERVAL) {
            try {
                const healthUrl = PROXY_HEALTH_URL;
                const healthResponse = await fetch(healthUrl, { method: 'GET', headers: { 'Content-Type': 'application/json' } });
                if (!healthResponse.ok) throw new Error(`Health check failed: ${healthResponse.status}`);
                console.log('✅ Server health check passed (via proxy)');
                lastHealthCheckedAt = Date.now();
            } catch (healthError) {
                console.warn('⚠️ Health check failed (via proxy):', healthError);
                lastHealthCheckedAt = Date.now(); // prevent immediate rechecks
                // Continue anyway - might be a temporary issue
            }
        }

        // Client-side retry for transient network issues
        const payload = JSON.stringify({ 
            query: message,
            conversation_history: conversationHistory,
            user_timezone: userTimezone,
            session_id: sessionId
        });

    let response = null;
    const maxAttempts = 3; // retry up to 3 times on network failure
        for (let attempt = 1; attempt <= maxAttempts; attempt++) {
            try {
                response = await fetch(apiUrl, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: payload });
                break; // success or server responded (we'll handle non-OK below)
            } catch (netErr) {
                console.warn('Network fetch attempt', attempt, netErr);
                if (attempt < maxAttempts) await new Promise(r => setTimeout(r, attempt * 250));
                else throw netErr; // let outer catch handle final failure
            }
        }

        // Read response text and try to parse JSON even on non-OK responses
        let data;
        try {
            const text = await response.text();
            try {
                data = text ? JSON.parse(text) : {};
            } catch (parseErr) {
                // Not JSON - wrap as a response string
                data = { response: text };
            }

            if (!response.ok) {
                // Show server-provided detail when available, otherwise map common HTTP codes
                let serverMsg = (data && (data.detail || data.error)) ? (data.detail || data.error) : null;
                if (!serverMsg) {
                    const statusMap = {
                        400: 'Bad Request (400)',
                        401: 'Unauthorized (401)',
                        403: 'Forbidden (403)',
                        404: 'Not Found (404)',
                        408: 'Request Timeout (408)',
                        429: 'Too Many Requests (429)',
                        500: 'Internal Server Error (500)',
                        502: 'Bad Gateway (502)',
                        503: 'Service Unavailable (503)',
                        504: 'Gateway Timeout (504)'
                    };
                    serverMsg = statusMap[response.status] || `Unexpected server response (${response.status})`;
                }
                console.warn('Server error:', serverMsg);
                try { showToast(`Server: ${serverMsg}`, 6000); } catch(e){}
                // Normalize fallback message for the UI
                data = { response: 'Sorry, the chatbot service is unavailable right now.' };
            }
        } catch (err) {
            console.error('Response parsing error:', err);
            data = { response: 'Sorry, there was an unexpected response from server.' };
        }
        hideTypingIndicator();
        
        // 🆕 NEW: Show extraction feedback if entities were detected
        if (data.entities && data.entities.length > 0) {
            showEntityFeedback(data.entities);
        }
        
        // 📱 ENHANCED MESSAGE SPLITTING: Handle complete sentence bubbles with better error handling
        if (Array.isArray(data.response)) {
            // Handle array response format (new backend format)
            if (data.response.length === 1) {
                // Single message in array format
                const message = data.response[0] || 'Sorry, no response.';
                const formattedMessage = formatConversationalMessage(message);
                addMessage('bot', formattedMessage);
            } else {
                // Multiple messages in array format
                showStatus(`Sending ${data.response.length} complete messages...`, 'success');
                data.response.forEach((message, index) => {
                    setTimeout(() => {
                        const cleanMessage = message.trim();
                        if (cleanMessage) {
                            const formattedMessage = formatConversationalMessage(cleanMessage);
                            addMessage('bot', formattedMessage, index === 0, true);
                        }
                    }, index * 300);
                });
            }
        } else if (typeof data.response === 'string') {
            // Handle single message or legacy format
            if (data.response.includes('Part 1/') || data.response.includes('Part ')) {
                // Legacy format - convert to separate messages
                const parts = data.response.split('\n\n');
                const messages = [];
                
                parts.forEach(part => {
                    if (part.trim()) {
                        const partMatch = part.match(/^Part \d+\/\d+:\s*(.*)$/);
                        if (partMatch) {
                            messages.push(partMatch[1].trim());
                        } else {
                            messages.push(part.trim());
                        }
                    }
                });
                
                if (messages.length > 1) {
                    showStatus(`Converting to ${messages.length} complete messages...`, 'success');
                    messages.forEach((message, index) => {
                        setTimeout(() => {
                            const formattedMessage = formatConversationalMessage(message);
                            addMessage('bot', formattedMessage, index === 0, true);
                        }, index * 300);
                    });
                } else {
                    const formattedMessage = formatConversationalMessage(data.response);
                    addMessage('bot', formattedMessage);
                }
            } else {
                // Single complete message
                const formattedMessage = formatConversationalMessage(data.response || 'Sorry, no response.');
                addMessage('bot', formattedMessage);
            }
        } else {
            // Fallback for unexpected format
            addMessage('bot', 'Sorry, no response.');
        }
    } catch (error) {
        console.error('chat error', error);
        hideTypingIndicator();
        
        // 🚨 ENHANCED ERROR HANDLING: More specific error messages
        let errorMessage = 'Sorry, I encountered an error. Please try again later.';
        let statusMessage = 'Connection error. Please try again.';
        
        if (error.name === 'TypeError' && error.message.includes('fetch')) {
            if (error.message.includes('CORS')) {
                errorMessage = 'CORS policy blocked the request. The server may be down or misconfigured.';
                statusMessage = 'CORS error. Server may be unavailable.';
            } else {
                errorMessage = 'Unable to connect to the server. Please check your internet connection.';
                statusMessage = 'Network error. Please check your connection.';
            }
        } else if (error.message.includes('HTTP error')) {
            errorMessage = 'Server error occurred. Please try again in a moment.';
            statusMessage = 'Server error. Please try again.';
        } else if (error.message.includes('Failed to fetch')) {
            errorMessage = 'Network connection failed. Please check your internet connection and try again.';
            statusMessage = 'Connection failed. Please check your network.';
        }
        
        addMessage('bot', errorMessage);
        showStatus(statusMessage, 'error');
    } finally {
        isWaitingForResponse = false;
        setInputDisabled(false);
    }
}

function handleKeyPress(event) {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        if (isWaitingForResponse) {
            showToast('Please wait for the assistant to finish its response before sending another message.', 2500);
            return;
        }
        sendMessage();
    }
}

// Load saved chat history on open and ensure typing state cleared
document.addEventListener('DOMContentLoaded', function() {
    loadHistory();
    isWaitingForResponse = false;
    hideTypingIndicator();
    const input = document.getElementById('messageInput');
    if (input) input.addEventListener('input', function() { autoResize(this); });
});
</script>

<!-- Chatbot widget styles (reuse from chatbot.php) -->
<style>
/* ...copy the relevant CSS from chatbot.php for .chat-header, .chat-messages, .message, etc... */
#chatbotWidgetBtn:hover {
    box-shadow: 0 8px 24px rgba(102,126,234,0.25);
    transform: scale(1.05);
    border: 1px solid #764ba2;
}

.chat-header {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    padding: 8px 16px 6px 16px; /* reduced padding */
    text-align: center;
    position: relative;
}
.chat-header h1 {
    font-size: 1em; /* slightly smaller */
    margin-bottom: 0px;
}
.chat-header p {
    opacity: 0.9;
    font-size: 0.8em; /* slightly smaller */
    margin-bottom: 0;
}
.chat-messages { flex: 1; padding: 16px; overflow-y: auto; background: #f8f9fa; scroll-behavior: smooth; height: 260px; }
.message { margin-bottom: 12px; display: flex; flex-direction: column; align-items: flex-start; animation: slideIn 0.3s ease; }
.message.user { align-items: flex-end; }
.message.bot { align-items: flex-start; }
.message-content { max-width: 70%; padding: 10px 15px; border-radius: 18px; word-wrap: break-word; position: relative; background: white; color: #333; border: 1px solid #e0e0e0; }
.message.user .message-content { background: #667eea; color: white; border: none; }
/* 📱 IMPROVED MESSAGE SPLITTING: Style for complete sentence bubbles */
.message.bot.split-message .message-content { 
    border-left: 3px solid #667eea; 
    background: #f8f9ff; 
    margin-left: 5px;
    animation: slideInFromLeft 0.4s ease;
}
.message.bot.split-message:first-of-type .message-content { 
    border-left: none; 
    background: white; 
    margin-left: 0;
    animation: slideIn 0.3s ease;
}
.message.bot.split-message:not(:first-of-type) .message-content {
    margin-top: 2px;
    border-radius: 18px 18px 18px 6px;
}
.message.bot.split-message:last-of-type .message-content {
    border-radius: 18px 18px 6px 18px;
}
.message-time { font-size: 0.7em; opacity: 0.7; margin-top: 6px; }
.typing-indicator { display: none; align-items: center; padding: 10px 18px; background: white; border-radius: 18px; border: 1px solid #e0e0e0; max-width: 70px; margin-bottom: 15px; }
.typing-indicator.active { display: flex; }
.typing-dots { display: flex; gap: 3px; }
.typing-dot { width: 6px; height: 6px; border-radius: 50%; background: #667eea; animation: typing 1.4s infinite; }
.typing-dot:nth-child(2) { animation-delay: 0.2s; }
.typing-dot:nth-child(3) { animation-delay: 0.4s; }
.chat-input-container { background: white; padding: 12px 16px; border-top: 1px solid #e0e0e0; }
.form-row input:focus { border-color: #667eea; }
.chat-input { display: flex; align-items: flex-end; gap: 8px; }
.chat-input textarea { 
    flex: 1; 
    padding: 10px 15px; 
    border: 1px solid #e0e0e0; 
    border-radius: 20px; 
    font-size: 1em; 
    outline: none; 
    transition: border-color 0.3s;
    resize: none;
    min-height: 20px;
    max-height: 120px;
    line-height: 1.4;
    font-family: inherit;
    overflow-y: auto;
}
.chat-input textarea:focus { border-color: #667eea; }
.send-btn { background: #667eea; color: white; border: none; border-radius: 50%; width: 40px; height: 40px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: background 0.3s, transform 0.2s; }
.send-btn:hover { background: #5a6fd8; transform: scale(1.05); }
.send-btn:active { transform: scale(0.95); }
.status-indicator { display: none; padding: 8px 12px; border-radius: 10px; margin-bottom: 8px; font-size: 0.9em; text-align: center; }
.status-indicator.success { display: block; background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.status-indicator.error { display: block; background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
@keyframes slideIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
@keyframes slideInFromLeft { from { opacity: 0; transform: translateX(-20px) translateY(10px); } to { opacity: 1; transform: translateX(0) translateY(0); } }
@keyframes typing { 0%, 60%, 100% { transform: translateY(0); } 30% { transform: translateY(-10px); } }
.welcomee-message { text-align: center; padding: 10px; color: #666; font-style: italic; }
.powered-by { text-align: center; padding: 6px; font-size: 0.8em; color: #999; background: #f8f9fa; border-top: 1px solid #e0e0e0; }
</style>