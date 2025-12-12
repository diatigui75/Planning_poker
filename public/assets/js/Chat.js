/**
 * Chat en temps réel - Planning Poker
 * Gestion complète du chat avec synchronisation en temps réel
 */

// Variables globales du chat
let chatOpen = false;
let lastMessageId = 0;
let unreadCount = 0;
let chatPollingInterval = null;
let playerName = '';

// Constantes
const CHAT_POLLING_INTERVAL = 2000; // 2 secondes
const MAX_MESSAGE_LENGTH = 1000;

/**
 * Initialiser le chat
 */
function initChat() {
    console.log('Initialisation du chat...');
    
    // Récupérer le nom du joueur depuis la page
    const playerNameElement = document.querySelector('.player-name');
    if (playerNameElement) {
        playerName = playerNameElement.textContent.trim();
    }
    
    // Charger les messages initiaux
    loadMessages();
    
    // Démarrer le polling pour les nouveaux messages
    startChatPolling();
    
    // Gérer l'envoi de messages
    const chatForm = document.getElementById('chat-form');
    if (chatForm) {
        chatForm.addEventListener('submit', handleChatSubmit);
    }
    
    // Gérer la touche Entrée
    const chatInput = document.getElementById('chat-input');
    if (chatInput) {
        chatInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                handleChatSubmit(e);
            }
        });
    }
}

/**
 * Basculer l'ouverture/fermeture du chat
 */
function toggleChat() {
    chatOpen = !chatOpen;
    
    const chatPanel = document.getElementById('chat-panel');
    const chatIcon = document.getElementById('chat-icon');
    
    if (chatOpen) {
        chatPanel.classList.add('open');
        chatIcon.classList.add('active');
        
        // Marquer tous les messages comme lus
        markMessagesAsRead();
        
        // Scroller vers le bas
        setTimeout(() => {
            scrollToBottom();
        }, 100);
    } else {
        chatPanel.classList.remove('open');
        chatIcon.classList.remove('active');
    }
}

/**
 * Charger les messages
 */
async function loadMessages(sinceId = 0) {
    try {
        const url = sinceId > 0 
            ? `${API_URL}?action=get_messages&since_id=${sinceId}`
            : `${API_URL}?action=get_messages`;
        
        const response = await fetch(url);
        const data = await response.json();
        
        console.log('Messages reçus:', data);
        
        if (data.success && data.messages && data.messages.length > 0) {
            displayMessages(data.messages, sinceId > 0);
            
            // Mettre à jour le dernier ID de message
            const maxId = Math.max(...data.messages.map(m => m.id));
            if (maxId > lastMessageId) {
                lastMessageId = maxId;
            }
            
            // Si le chat est ouvert, scroller vers le bas
            if (chatOpen) {
                scrollToBottom();
            } else {
                // Sinon, incrémenter le compteur de non-lus
                if (sinceId > 0) {
                    unreadCount += data.messages.length;
                    updateUnreadBadge();
                }
            }
        } else if (data.success && data.messages && data.messages.length === 0) {
            console.log('Aucun nouveau message');
        } else if (!data.success) {
            console.error('Erreur API:', data.error);
        }
    } catch (error) {
        console.error('Erreur chargement messages:', error);
    }
}

/**
 * Afficher les messages dans le chat
 */
function displayMessages(messages, append = false) {
    const messagesContainer = document.getElementById('chat-messages');
    
    if (!messagesContainer) return;
    
    // Supprimer le message "chat-empty" s'il existe
    const emptyMessage = messagesContainer.querySelector('.chat-empty');
    if (emptyMessage) {
        emptyMessage.remove();
    }
    
    if (!append) {
        messagesContainer.innerHTML = '';
    }
    
    messages.forEach(message => {
        const messageElement = createMessageElement(message);
        messagesContainer.appendChild(messageElement);
    });
}

/**
 * Créer un élément de message
 */
function createMessageElement(message) {
    const div = document.createElement('div');
    div.className = 'chat-message';
    div.dataset.messageId = message.id;
    
    // Déterminer si c'est notre message
    const isOwn = message.player_id == PLAYER_ID;
    if (isOwn) {
        div.classList.add('own-message');
    }
    
    // Avatar
    const avatar = document.createElement('div');
    avatar.className = 'chat-avatar';
    avatar.textContent = message.player_name.charAt(0).toUpperCase();
    if (message.is_scrum_master) {
        avatar.classList.add('scrum-master');
        avatar.title = 'Scrum Master';
    }
    
    // Contenu du message
    const content = document.createElement('div');
    content.className = 'chat-message-content';
    
    // Header avec nom et heure
    const header = document.createElement('div');
    header.className = 'chat-message-header';
    
    const name = document.createElement('span');
    name.className = 'chat-message-name';
    name.textContent = message.player_name;
    if (message.is_scrum_master) {
        name.innerHTML += ' <i class="fas fa-crown" title="Scrum Master"></i>';
    }
    
    const time = document.createElement('span');
    time.className = 'chat-message-time';
    time.textContent = formatMessageTime(message.created_at);
    
    header.appendChild(name);
    header.appendChild(time);
    
    // Texte du message
    const text = document.createElement('div');
    text.className = 'chat-message-text';
    text.textContent = message.content;
    
    content.appendChild(header);
    content.appendChild(text);
    
    if (!isOwn) {
        div.appendChild(avatar);
    }
    div.appendChild(content);
    if (isOwn) {
        div.appendChild(avatar);
    }
    
    return div;
}

/**
 * Formater l'heure d'un message
 */
function formatMessageTime(timestamp) {
    const date = new Date(timestamp);
    const now = new Date();
    
    const diffMs = now - date;
    const diffMins = Math.floor(diffMs / 60000);
    
    if (diffMins < 1) return 'À l\'instant';
    if (diffMins < 60) return `Il y a ${diffMins} min`;
    
    const hours = date.getHours().toString().padStart(2, '0');
    const minutes = date.getMinutes().toString().padStart(2, '0');
    
    return `${hours}:${minutes}`;
}

/**
 * Gérer l'envoi d'un message
 */
async function handleChatSubmit(e) {
    e.preventDefault();
    
    const input = document.getElementById('chat-input');
    const content = input.value.trim();
    
    if (!content) return;
    
    if (content.length > MAX_MESSAGE_LENGTH) {
        showNotification(`<i class="fas fa-exclamation-triangle"></i> Message trop long (max ${MAX_MESSAGE_LENGTH} caractères)`, 'error');
        return;
    }
    
    // Désactiver le champ pendant l'envoi
    input.disabled = true;
    
    try {
        const formData = new FormData();
        formData.append('action', 'send_message');
        formData.append('content', content);
        
        const response = await fetch(API_URL, {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        console.log('Réponse envoi message:', data);
        
        if (data.success) {
            // Vider le champ
            input.value = '';
            
            console.log('Message envoyé avec succès, rechargement...');
            
            // Recharger les messages immédiatement (tous les messages, pas juste les nouveaux)
            await loadMessages(0);
            
            // Scroller vers le bas
            scrollToBottom();
        } else {
            showNotification(`<i class="fas fa-times-circle"></i> ${data.error}`, 'error');
        }
    } catch (error) {
        console.error('Erreur envoi message:', error);
        showNotification('<i class="fas fa-times-circle"></i> Erreur lors de l\'envoi du message', 'error');
    } finally {
        // Réactiver le champ
        input.disabled = false;
        input.focus();
    }
}

/**
 * Scroller vers le bas du chat
 */
function scrollToBottom() {
    const messagesContainer = document.getElementById('chat-messages');
    if (messagesContainer) {
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }
}

/**
 * Marquer les messages comme lus
 */
function markMessagesAsRead() {
    unreadCount = 0;
    updateUnreadBadge();
}

/**
 * Mettre à jour le badge de messages non lus
 */
function updateUnreadBadge() {
    const badge = document.getElementById('chat-unread-badge');
    if (badge) {
        if (unreadCount > 0) {
            badge.textContent = unreadCount > 99 ? '99+' : unreadCount;
            badge.style.display = 'flex';
        } else {
            badge.style.display = 'none';
        }
    }
}

/**
 * Démarrer le polling pour les nouveaux messages
 */
function startChatPolling() {
    // Arrêter le polling existant s'il y en a un
    if (chatPollingInterval) {
        clearInterval(chatPollingInterval);
    }
    
    // Démarrer un nouveau polling
    chatPollingInterval = setInterval(() => {
        if (lastMessageId > 0) {
            loadMessages(lastMessageId);
        }
    }, CHAT_POLLING_INTERVAL);
}

/**
 * Arrêter le polling
 */
function stopChatPolling() {
    if (chatPollingInterval) {
        clearInterval(chatPollingInterval);
        chatPollingInterval = null;
    }
}

/**
 * Afficher le nombre de personnes en ligne dans le header du chat
 */
function updateChatOnlineCount(count) {
    const onlineCountElement = document.getElementById('chat-online-count');
    if (onlineCountElement) {
        onlineCountElement.textContent = `${count} en ligne`;
    }
}

// Initialiser le chat au chargement de la page
document.addEventListener('DOMContentLoaded', () => {
    initChat();
    
    // Nettoyer au déchargement de la page
    window.addEventListener('beforeunload', () => {
        stopChatPolling();
    });
});