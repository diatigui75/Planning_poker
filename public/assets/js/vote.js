// Configuration de l'API
const API_URL = 'api.php';

// Variables globales
let pollingInterval = null;
let lastSessionStatus = null;
let lastStoryId = null;
let lastVotesCount = 0;
let lastStoriesCount = 0;
let isInitialLoad = true; // Pour ne pas afficher de notif au premier chargement
let isModalOpen = false; // Pour bloquer le rechargement automatique pendant que le modal est ouvert
let isValidating = false; // Pour indiquer qu'on est en train de valider
let currentSessionData = null; // Stocker les données de session

// Polling pour mettre à jour l'état
function startPolling() {
    if (pollingInterval) {
        clearInterval(pollingInterval);
    }
    pollingInterval = setInterval(updateSessionState, 2000); // Toutes les 2 secondes
}

// Charger l'état initial au chargement de la page
window.addEventListener('DOMContentLoaded', () => {
    console.log('Planning Poker - Initialisation');
    
    // Récupérer l'ID de la story actuelle au chargement
    const storyCard = document.querySelector('.story-card[data-story-id]');
    if (storyCard) {
        lastStoryId = parseInt(storyCard.dataset.storyId);
    }
    
    // Compter les stories au chargement
    const storiesList = document.querySelectorAll('.story-item');
    lastStoriesCount = storiesList.length;
    
    updateSessionState();
    
    // Marquer comme chargé après 2 secondes
    setTimeout(() => {
        isInitialLoad = false;
    }, 2000);
    
    startPolling();
});

/**
 * Voter pour une carte
 */
async function voteCard(value) {
    try {
        const formData = new FormData();
        formData.append('action', 'cast_vote');
        formData.append('vote_value', value);
        
        const response = await fetch(API_URL, {
            method: 'POST',
            body: formData
        });
        
        // Vérifier si la réponse HTTP est OK
        if (!response.ok) {
            throw new Error(`Erreur HTTP: ${response.status}`);
        }
        
        // Vérifier le type de contenu
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            const text = await response.text();
            console.error('Réponse non-JSON:', text);
            throw new Error('Réponse invalide du serveur');
        }
        
        const data = await response.json();
        
        if (!data) {
            throw new Error('Réponse vide du serveur');
        }
        
        if (data.success) {
            showNotification('✅ Vote enregistré !', 'success');
            // Désactiver tous les boutons de vote
            document.querySelectorAll('.card-vote').forEach(btn => {
                btn.disabled = true;
                btn.classList.add('disabled');
            });
            updateSessionState();
        } else {
            showNotification('❌ ' + (data.error || 'Erreur inconnue'), 'error');
        }
    } catch (error) {
        console.error('Erreur:', error);
        showNotification('❌ Erreur lors du vote: ' + error.message, 'error');
    }
}

/**
 * Révéler les votes (Scrum Master)
 */
async function revealVotes() {
    if (!isScrumMaster) {
        showNotification('❌ Action réservée au Scrum Master', 'error');
        return;
    }

    try {
        const formData = new FormData();
        formData.append('action', 'reveal_votes');
        
        const response = await fetch(API_URL, {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            if (data.coffee_break) {
                showNotification('☕ Pause café ! Session sauvegardée.', 'info');
                setTimeout(() => location.reload(), 2000);
            } else {
                displayRevealModal(data);
            }
        } else {
            showNotification('❌ ' + data.error, 'error');
        }
    } catch (error) {
        console.error('Erreur:', error);
        showNotification('❌ Erreur lors de la révélation', 'error');
    }
}

/**
 * Afficher le modal de révélation
 */
function displayRevealModal(data) {
    const modal = document.getElementById('reveal-modal');
    const content = document.getElementById('reveal-content');
    
    // Bloquer le polling automatique pendant que le modal est ouvert
    isModalOpen = true;
    
    let html = '<div class="votes-reveal">';
    html += '<h4>Votes des joueurs:</h4>';
    html += '<div class="votes-grid">';
    
    data.votes.forEach(vote => {
        html += `
            <div class="vote-card">
                <div class="vote-value">${vote.vote_value}</div>
                <div class="vote-player">${vote.pseudo}</div>
            </div>
        `;
    });
    
    html += '</div>';
    
    // Résultat
    if (data.result) {
        if (data.result.valid) {
            html += `
                <div class="result-box success">
                    <h4>✅ ${data.result.reason}</h4>
                    <p class="result-value">Estimation proposée: <strong>${data.result.value}</strong> points</p>
                </div>
            `;
            
            if (isScrumMaster) {
                html += `
                    <div class="modal-actions">
                        <button onclick="validateEstimation(${data.result.value})" class="btn btn-success">
                            ✓ Valider cette estimation
                        </button>
                        <button onclick="revote()" class="btn btn-warning">
                            🔄 Revoter
                        </button>
                    </div>
                `;
            }
        } else {
            html += `
                <div class="result-box warning">
                    <h4>⚠️ ${data.result.reason}</h4>
                    <p>Discussion nécessaire avant de continuer.</p>
                </div>
            `;
            
            if (isScrumMaster) {
                html += `
                    <div class="modal-actions">
                        <button onclick="revote()" class="btn btn-warning">
                            🔄 Lancer un nouveau vote
                        </button>
                        <button onclick="customEstimation()" class="btn btn-secondary">
                            ✏️ Forcer une estimation
                        </button>
                    </div>
                `;
            }
        }
    }
    
    html += '</div>';
    
    content.innerHTML = html;
    modal.style.display = 'flex';
}

/**
 * Valider l'estimation
 */
async function validateEstimation(estimation) {
    const storyId = getCurrentStoryId();
    if (!storyId) {
        showNotification('❌ Aucune story en cours', 'error');
        return;
    }

    try {
        // Activer le flag de validation AVANT l'appel API
        isValidating = true;
        
        const formData = new FormData();
        formData.append('action', 'validate_estimation');
        formData.append('story_id', storyId);
        formData.append('estimation', estimation);
        
        const response = await fetch(API_URL, {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification('✅ Estimation validée !', 'success');
            closeRevealModal();
            
            // Pour le Scrum Master, recharger la page après un court délai
            setTimeout(() => {
                isValidating = false;
                location.reload();
            }, 1000);
        } else {
            showNotification('❌ ' + data.error, 'error');
            isValidating = false;
        }
    } catch (error) {
        console.error('Erreur:', error);
        showNotification('❌ Erreur lors de la validation', 'error');
        isValidating = false;
    }
}

/**
 * Estimation personnalisée
 */
function customEstimation() {
    const estimation = prompt('Entrez l\'estimation finale (en points):');
    if (estimation && !isNaN(estimation)) {
        validateEstimation(parseInt(estimation));
    }
}

/**
 * Revoter
 */
async function revote() {
    if (!confirm('Lancer un nouveau tour de vote ?')) {
        return;
    }

    try {
        const formData = new FormData();
        formData.append('action', 'revote');
        
        const response = await fetch(API_URL, {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification('🔄 Nouveau tour de vote lancé', 'success');
            closeRevealModal();
            setTimeout(() => location.reload(), 1000);
        } else {
            showNotification('❌ ' + data.error, 'error');
        }
    } catch (error) {
        console.error('Erreur:', error);
        showNotification('❌ Erreur', 'error');
    }
}

/**
 * Mettre à jour l'état de la session
 */
async function updateSessionState() {
    try {
        const response = await fetch(API_URL + '?action=get_session_state');
        
        // Vérifier si la réponse est OK
        if (!response.ok) {
            console.error('Erreur HTTP:', response.status);
            return;
        }
        
        // Vérifier le type de contenu
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            console.error('Réponse non-JSON reçue:', contentType);
            const text = await response.text();
            console.error('Contenu:', text.substring(0, 200));
            return;
        }
        
        const data = await response.json();
        
        if (data.success) {
            // Sauvegarder l'état complet
            currentSessionData = data;
            
            // Récupérer l'ID de la story actuelle
            const currentStoryId = data.story ? data.story.id : null;
            
            // Détecter les changements
            const statusChanged = lastSessionStatus && lastSessionStatus !== data.session.status;
            const storyChanged = lastStoryId && currentStoryId && lastStoryId !== currentStoryId;
            const newVotes = data.vote_info && data.vote_info.votes_count > lastVotesCount;
            const storiesCountChanged = data.stats && data.stats.total !== lastStoriesCount;
            
            // Debug log amélioré
            if (storyChanged || statusChanged) {
                console.log('=== CHANGEMENT DÉTECTÉ ===', {
                    storyChanged,
                    statusChanged,
                    lastStoryId,
                    currentStoryId,
                    lastStatus: lastSessionStatus,
                    currentStatus: data.session.status,
                    isValidating,
                    isModalOpen,
                    isInitialLoad,
                    isScrumMaster: typeof isScrumMaster !== 'undefined' ? isScrumMaster : 'undefined'
                });
            }
            
            // Mettre à jour les variables de suivi
            lastSessionStatus = data.session.status;
            lastVotesCount = data.vote_info ? data.vote_info.votes_count : 0;
            
            // SESSION TERMINÉE → Recharger pour tous
            if (statusChanged && data.session.status === 'finished' && !isInitialLoad) {
                console.log('🏁 Session terminée détectée');
                
                // Notification avant rechargement
                showNotification('🎉 Toutes les stories sont estimées !', 'success');
                
                // Recharger après un court délai pour laisser voir la notification
                setTimeout(() => {
                    location.reload();
                }, 2000);
                
                return; // Arrêter le traitement
            }
            
            // IMPORT DE NOUVELLES STORIES DÉTECTÉ
            if (storiesCountChanged && !isInitialLoad) {
                console.log('📥 IMPORT DÉTECTÉ:', {
                    ancienTotal: lastStoriesCount,
                    nouveauTotal: data.stats.total,
                    isValidating: isValidating,
                    isScrumMaster: typeof isScrumMaster !== 'undefined' ? isScrumMaster : 'undefined',
                    hasCurrentStory: !!data.story
                });
                
                lastStoriesCount = data.stats.total;
                
                // UNE SEULE notification pour l'import (sauf celui qui a importé)
                if (!isValidating) {
                    showNotification('📥 Nouvelles user stories importées !', 'info');
                    playNotificationSound();
                }
                
                // Recharger le backlog pour tout le monde
                await reloadBacklogList();
                
                // Mettre à jour la barre de progression
                updateProgressBar(data.stats);
                showProgressSection(data.stats);
                
                // Si aucune story n'était en cours et qu'il y en a maintenant une
                if (!lastStoryId && data.story) {
                    console.log('Première story détectée:', data.story);
                    lastStoryId = data.story.id;
                    
                    // Afficher la section de story
                    showStorySection(data.story);
                    
                    // Notification supplémentaire SEULEMENT pour la première story
                    showNotification('📋 Première story prête à être estimée !', 'success');
                }
            }
            
            // CHANGEMENT DE STORY DÉTECTÉ (après validation ou autre action)
            // Condition simplifiée : story a changé ET on n'est pas en chargement initial ET modal fermé
            if (storyChanged && !isInitialLoad && !isModalOpen) {
                console.log('📋 CHANGEMENT DE STORY DÉTECTÉ:', {
                    de: lastStoryId,
                    vers: currentStoryId,
                    statut: data.session.status,
                    isScrumMaster: typeof isScrumMaster !== 'undefined' ? isScrumMaster : 'undefined'
                });
                
                // Mettre à jour l'ID IMMÉDIATEMENT pour éviter les doubles détections
                lastStoryId = currentStoryId;
                
                // UNE SEULE notification selon le rôle
                if (typeof isScrumMaster !== 'undefined' && !isScrumMaster) {
                    // Pour les joueurs : notification d'une nouvelle story
                    showNotification('📋 Nouvelle story à estimer !', 'info');
                    playNotificationSound();
                } else if (typeof isScrumMaster !== 'undefined' && isScrumMaster && !isValidating) {
                    // Pour le SM : seulement si ce n'est pas lui qui vient de valider
                    showNotification('📋 Story suivante chargée', 'success');
                }
                
                // Mettre à jour la story affichée dynamiquement
                if (data.story) {
                    updateCurrentStory(data.story);
                }
                
                // Réinitialiser les votes
                resetVotingInterface();
                
                // Mettre à jour le backlog
                await reloadBacklogList();
                
                // Mettre à jour la progression
                updateProgressBar(data.stats);
                
                // Réinitialiser le flag de validation après un court délai
                if (isValidating) {
                    setTimeout(() => {
                        isValidating = false;
                    }, 1000);
                }
            }
            
            // Détection spéciale : Story disparue (dernière story validée)
            if (lastStoryId && !currentStoryId && !isInitialLoad && data.session.status !== 'finished') {
                console.log('⚠️ Story actuelle disparue, attente du statut finished...');
                // La story a disparu mais le statut n'est pas encore 'finished'
                // Le prochain polling détectera le changement de statut
            }
            
            // Mettre à jour lastStoryId si pas encore défini (APRÈS la détection de changement)
            if (currentStoryId && !lastStoryId) {
                lastStoryId = currentStoryId;
            }
            
            // Si plus de story et qu'on en avait une avant, mettre lastStoryId à null
            if (!currentStoryId && lastStoryId && data.session.status === 'finished') {
                lastStoryId = null;
            }
            
            // STATUS RÉVÉLÉ → Afficher le modal pour le SM uniquement
            if (statusChanged && data.session.status === 'revealed' && !isInitialLoad && !isModalOpen) {
                if (isScrumMaster) {
                    // Le SM vient de révéler, afficher le modal
                    // (cela sera géré par la fonction revealVotes)
                } else {
                    // Les autres joueurs attendent
                    showNotification('⏳ Le Scrum Master évalue les votes...', 'info');
                }
            }
            
            // RETOUR EN VOTING (revote)
            if (statusChanged && data.session.status === 'voting' && !isInitialLoad) {
                console.log('Revote détecté');
                resetVotingInterface();
                showNotification('🔄 Nouveau tour de vote !', 'warning');
            }
            
            // Mise à jour du compteur de votes
            updateVoteCount(data.vote_info);
            
            // Mise à jour de la liste des joueurs
            updatePlayersList(data.players, data.vote_info);
            
            // Mise à jour des statuts des joueurs
            updatePlayerVoteStatus(data.players, data.vote_info);
            
            // Mise à jour de la barre de progression
            updateProgressBar(data.stats);
            
            // Afficher une notification si de nouveaux votes arrivent
            if (newVotes && !isInitialLoad) {
                console.log('Nouveaux votes détectés');
                playNotificationSound();
            }
            
        } else {
            console.error('Erreur API:', data.error);
        }
    } catch (error) {
        console.error('Erreur mise à jour:', error);
    }
}

/**
 * Mettre à jour le compteur de votes
 */
function updateVoteCount(voteInfo) {
    if (!voteInfo) return;
    
    // Mettre à jour le compteur dans le bouton
    const voteCounter = document.getElementById('vote-counter');
    if (voteCounter) {
        const oldCount = parseInt(voteCounter.textContent) || 0;
        const newCount = voteInfo.votes_count || 0;
        
        if (newCount !== oldCount) {
            voteCounter.textContent = newCount;
            // Animation du compteur
            voteCounter.style.animation = 'pulse 0.5s';
            setTimeout(() => {
                voteCounter.style.animation = '';
            }, 500);
        }
    }
    
    // Activer/désactiver le bouton révéler selon le nombre de votes
    const revealBtn = document.querySelector('[onclick*="revealVotes"]');
    if (revealBtn) {
        if (voteInfo.votes_count > 0) {
            revealBtn.disabled = false;
            revealBtn.style.opacity = '1';
        } else {
            revealBtn.disabled = true;
            revealBtn.style.opacity = '0.5';
        }
    }
}

/**
 * Mettre à jour la liste des joueurs
 */
function updatePlayersList(players, voteInfo) {
    if (!players) return;
    
    const playersGrid = document.getElementById('players-grid');
    const playersCount = document.getElementById('players-count');
    
    if (!playersGrid) return;
    
    // Mettre à jour le compteur
    if (playersCount) {
        const currentCount = parseInt(playersCount.textContent) || 0;
        const newCount = players.length;
        
        if (newCount !== currentCount) {
            playersCount.textContent = newCount;
            
            // Animation du compteur
            playersCount.style.animation = 'pulse 0.5s';
            setTimeout(() => {
                playersCount.style.animation = '';
            }, 500);
            
            // Notification si nouveau joueur
            if (newCount > currentCount && !isInitialLoad) {
                showNotification('👤 Un nouveau joueur a rejoint !', 'info');
                playNotificationSound();
            }
        }
    }
    
    // Vérifier si la structure des joueurs a changé
    const existingPlayerIds = Array.from(playersGrid.querySelectorAll('.player-card'))
        .map(card => parseInt(card.dataset.playerId));
    
    const newPlayerIds = players.map(p => p.id);
    
    // Si les joueurs ont changé, reconstruire la grille
    if (JSON.stringify(existingPlayerIds.sort()) !== JSON.stringify(newPlayerIds.sort())) {
        rebuildPlayersGrid(players, voteInfo);
    }
}

/**
 * Reconstruire complètement la grille des joueurs
 */
function rebuildPlayersGrid(players, voteInfo) {
    const playersGrid = document.getElementById('players-grid');
    if (!playersGrid) return;
    
    const votedPlayerIds = voteInfo && voteInfo.votes ? voteInfo.votes.map(v => v.player_id) : [];
    
    playersGrid.innerHTML = '';
    
    players.forEach(player => {
        const hasVoted = votedPlayerIds.includes(player.id);
        
        const playerCard = document.createElement('div');
        playerCard.className = `player-card ${player.is_connected ? 'connected' : 'disconnected'}`;
        playerCard.dataset.playerId = player.id;
        
        playerCard.innerHTML = `
            <span class="player-icon">${player.is_scrum_master ? '👑' : '👤'}</span>
            <span class="player-pseudo">${escapeHtml(player.pseudo)}</span>
            <span class="vote-status ${hasVoted ? '' : 'pending'}">${hasVoted ? '✅' : '⏳'}</span>
        `;
        
        // Animation d'apparition pour les nouveaux joueurs
        playerCard.style.animation = 'fadeIn 0.5s ease-out';
        
        playersGrid.appendChild(playerCard);
    });
}

/**
 * Échapper le HTML pour éviter les injections
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Mettre à jour le statut de vote des joueurs
 */
function updatePlayerVoteStatus(players, voteInfo) {
    if (!voteInfo || !voteInfo.votes) return;
    
    const votedPlayerIds = voteInfo.votes.map(v => v.player_id);
    
    players.forEach(player => {
        const playerCard = document.querySelector(`.player-card[data-player-id="${player.id}"]`);
        if (playerCard) {
            const statusIcon = playerCard.querySelector('.vote-status');
            if (statusIcon) {
                const hasVoted = votedPlayerIds.includes(player.id);
                const wasVoted = statusIcon.textContent === '✅';
                
                if (hasVoted && !wasVoted) {
                    // Nouveau vote détecté
                    statusIcon.textContent = '✅';
                    statusIcon.classList.remove('pending');
                    playerCard.classList.add('voted');
                    
                    // Animation de notification
                    playerCard.style.animation = 'pulse 0.5s';
                    setTimeout(() => {
                        playerCard.style.animation = '';
                    }, 500);
                } else if (!hasVoted && wasVoted) {
                    // Vote retiré (cas du revote)
                    statusIcon.textContent = '⏳';
                    statusIcon.classList.add('pending');
                    playerCard.classList.remove('voted');
                }
            }
        }
    });
}

/**
 * Mettre à jour la barre de progression
 */
function updateProgressBar(stats) {
    if (!stats || stats.total === 0) return;
    
    const percentage = (stats.estimated / stats.total) * 100;
    const progressBar = document.querySelector('.progress-bar');
    const progressText = document.getElementById('progress-text');
    
    if (progressBar) {
        // Animer le changement
        progressBar.style.transition = 'width 0.5s ease-out';
        progressBar.style.width = percentage + '%';
    }
    
    if (progressText) {
        const newText = `${stats.estimated}/${stats.total}`;
        if (progressText.textContent !== newText) {
            progressText.textContent = newText;
            // Animation
            progressText.style.animation = 'pulse 0.5s';
            setTimeout(() => {
                progressText.style.animation = '';
            }, 500);
        }
    } else {
        // Si la barre n'existe pas, la créer
        showProgressSection(stats);
    }
}

/**
 * Afficher l'overlay de transition
 */
function showTransitionOverlay() {
    const overlay = document.getElementById('transition-overlay');
    if (overlay) {
        overlay.classList.add('active');
    }
}

/**
 * Masquer l'overlay de transition
 */
function hideTransitionOverlay() {
    const overlay = document.getElementById('transition-overlay');
    if (overlay) {
        overlay.classList.remove('active');
    }
}

/**
 * Afficher la section de progression
 */
function showProgressSection(stats) {
    if (!stats || stats.total === 0) return;
    
    let progressContainer = document.querySelector('.progress-container');
    
    if (!progressContainer) {
        // Créer la section de progression
        const playersSection = document.querySelector('.players-section');
        
        if (!playersSection) return;
        
        progressContainer = document.createElement('div');
        progressContainer.className = 'progress-container';
        progressContainer.style.animation = 'fadeIn 0.5s ease-out';
        
        progressContainer.innerHTML = `
            <div class="progress-info">
                <span>Progression: <span id="progress-text">${stats.estimated}/${stats.total}</span> stories estimées</span>
            </div>
            <div class="progress-bar-container">
                <div class="progress-bar" style="width: ${(stats.estimated / stats.total) * 100}%"></div>
            </div>
        `;
        
        // Insérer AVANT la section des joueurs (pas après!)
        playersSection.insertAdjacentElement('beforebegin', progressContainer);
    } else {
        // Mettre à jour la progression existante
        const progressText = document.getElementById('progress-text');
        if (progressText) {
            progressText.textContent = `${stats.estimated}/${stats.total}`;
        }
        
        // Mettre à jour la barre
        const progressBar = progressContainer.querySelector('.progress-bar');
        if (progressBar) {
            const percentage = (stats.estimated / stats.total) * 100;
            progressBar.style.width = percentage + '%';
        }
    }
}

/**
 * Afficher la section de story
 */
function showStorySection(story) {
    if (!story) return;
    
    // Supprimer la section "Aucune story" si elle existe
    const noStorySection = document.querySelector('.no-story-section');
    if (noStorySection) {
        noStorySection.style.animation = 'fadeOut 0.3s ease-out';
        setTimeout(() => {
            noStorySection.remove();
        }, 300);
    }
    
    let storySection = document.querySelector('.story-section');
    
    if (!storySection) {
        // Créer la section de story
        const playersSection = document.querySelector('.players-section');
        const backlogSection = document.querySelector('.backlog-section');
        
        if (!playersSection) return;
        
        storySection = document.createElement('div');
        storySection.className = 'story-section';
        storySection.style.animation = 'fadeIn 0.5s ease-out';
        
        storySection.innerHTML = `
            <div class="story-card" data-story-id="${story.id}">
                <div class="story-header">
                    <h2>${escapeHtml(story.title)}</h2>
                    <span class="story-badge priority-${story.priority || 'moyenne'}">
                        ${story.priority ? story.priority.charAt(0).toUpperCase() + story.priority.slice(1) : 'Moyenne'}
                    </span>
                </div>
                <p class="story-description">${escapeHtml(story.description || '').replace(/\n/g, '<br>')}</p>
                <div class="story-meta">
                    <span>ID: ${escapeHtml(story.story_id)}</span>
                    <span>Votes: <span id="vote-counter-meta">0</span>/<span id="total-players">${document.querySelectorAll('.player-card').length}</span></span>
                </div>
            </div>

            <div class="voting-section">
                <h3>Choisissez votre carte</h3>
                <div class="cards-grid">
                    ${generateCardsHTML()}
                </div>
                
                ${typeof isScrumMaster !== 'undefined' && isScrumMaster ? `
                    <div class="sm-actions">
                        <button onclick="revealVotes()" class="btn btn-primary" data-vote-count disabled>
                            🔍 Révéler les votes (<span id="vote-counter">0</span>)
                        </button>
                    </div>
                ` : ''}
            </div>
        `;
        
        // TOUJOURS insérer APRÈS la section des joueurs
        // et AVANT le backlog s'il existe
        if (backlogSection) {
            backlogSection.insertAdjacentElement('beforebegin', storySection);
        } else {
            playersSection.insertAdjacentElement('afterend', storySection);
        }
    } else {
        // Mettre à jour la story existante
        updateCurrentStory(story);
    }
}

/**
 * Générer le HTML des cartes de vote
 */
function generateCardsHTML() {
    const cards = [
        {value: '0', display: '0'},
        {value: '1', display: '1'},
        {value: '2', display: '2'},
        {value: '3', display: '3'},
        {value: '5', display: '5'},
        {value: '8', display: '8'},
        {value: '13', display: '13'},
        {value: '20', display: '20'},
        {value: '40', display: '40'},
        {value: '100', display: '100'},
        {value: '?', display: '?'},
        {value: 'cafe', display: '☕'}
    ];
    
    return cards.map(card => `
        <button class="card-vote" 
                data-value="${card.value}"
                onclick="voteCard('${card.value}')">
            <span class="card-value">${card.display}</span>
        </button>
    `).join('');
}

/**
 * Mettre à jour la story affichée dynamiquement
 */
function updateCurrentStory(story) {
    if (!story) return;
    
    const storyCard = document.querySelector('.story-card');
    if (storyCard) {
        storyCard.dataset.storyId = story.id;
        
        const title = storyCard.querySelector('h2');
        if (title) title.textContent = story.title;
        
        const description = storyCard.querySelector('.story-description');
        if (description) {
            description.innerHTML = escapeHtml(story.description || '').replace(/\n/g, '<br>');
        }
        
        const storyIdSpan = storyCard.querySelector('.story-meta span:first-child');
        if (storyIdSpan) storyIdSpan.textContent = 'ID: ' + story.story_id;
        
        // Mettre à jour le badge de priorité
        const priorityBadge = storyCard.querySelector('.story-badge');
        if (priorityBadge) {
            priorityBadge.className = `story-badge priority-${story.priority || 'moyenne'}`;
            priorityBadge.textContent = story.priority ? 
                story.priority.charAt(0).toUpperCase() + story.priority.slice(1) : 'Moyenne';
        }
        
        // Animation
        storyCard.style.animation = 'fadeIn 0.5s ease-out';
        setTimeout(() => {
            storyCard.style.animation = '';
        }, 500);
        
        console.log('Story mise à jour:', story.title);
    } else {
        console.log('Story card non trouvée, création de la section story');
        showStorySection(story);
    }
}

/**
 * Réinitialiser l'interface de vote
 */
function resetVotingInterface() {
    // Réactiver toutes les cartes
    document.querySelectorAll('.card-vote').forEach(btn => {
        btn.disabled = false;
        btn.classList.remove('disabled');
    });
    
    // Masquer la confirmation de vote
    const voteConfirmation = document.querySelector('.vote-confirmation');
    if (voteConfirmation) {
        voteConfirmation.style.display = 'none';
    }
    
    // Réinitialiser le compteur de votes
    const voteCounter = document.getElementById('vote-counter');
    if (voteCounter) {
        voteCounter.textContent = '0';
    }
    
    // Réinitialiser les statuts des joueurs
    document.querySelectorAll('.vote-status').forEach(status => {
        status.textContent = '⏳';
        status.classList.add('pending');
    });
    
    document.querySelectorAll('.player-card').forEach(card => {
        card.classList.remove('voted');
    });
}

/**
 * Recharger la liste du backlog dynamiquement
 */
async function reloadBacklogList() {
    try {
        const response = await fetch(API_URL + '?action=get_backlog_list');
        const data = await response.json();
        
        if (data.success && data.stories) {
            const backlogSection = document.querySelector('.backlog-section');
            const storiesList = document.querySelector('.backlog-section .stories-list');
            
            // Si la section backlog n'existe pas encore, la créer
            if (!backlogSection) {
                createBacklogSection(data.stories);
                return;
            }
            
            if (!storiesList) return;
            
            // Vider la liste actuelle
            storiesList.innerHTML = '';
            
            // Ajouter toutes les stories avec animation
            data.stories.forEach((story, index) => {
                const storyItem = document.createElement('div');
                storyItem.className = `story-item status-${story.status}`;
                storyItem.style.animation = `fadeIn 0.5s ease-out ${index * 0.05}s`;
                storyItem.style.opacity = '0';
                storyItem.style.animationFillMode = 'forwards';
                
                storyItem.innerHTML = `
                    <div class="story-item-header">
                        <span class="story-item-id">${escapeHtml(story.story_id)}</span>
                        <span class="story-item-title">${escapeHtml(story.title)}</span>
                    </div>
                    <div class="story-item-footer">
                        <span class="story-item-priority priority-${story.priority}">
                            ${story.priority.charAt(0).toUpperCase() + story.priority.slice(1)}
                        </span>
                        ${story.estimation !== null 
                            ? `<span class="story-item-estimation">✓ ${story.estimation} pts</span>`
                            : `<span class="story-item-status">
                                ${story.status === 'voting' ? '⏳ En cours' : 
                                  (story.status === 'pending' ? '⏸️ En attente' : '✅ Estimée')}
                               </span>`
                        }
                    </div>
                `;
                
                storiesList.appendChild(storyItem);
            });
            
            console.log('Backlog mis à jour:', data.stories.length, 'stories');
        }
    } catch (error) {
        console.error('Erreur rechargement backlog:', error);
    }
}

/**
 * Créer la section backlog si elle n'existe pas
 */
function createBacklogSection(stories) {
    const container = document.querySelector('.vote-container');
    if (!container) return;
    
    const backlogSection = document.createElement('div');
    backlogSection.className = 'backlog-section';
    backlogSection.style.animation = 'fadeIn 0.5s ease-out';
    
    backlogSection.innerHTML = `
        <h3>Backlog complet</h3>
        <div class="stories-list"></div>
    `;
    
    // Toujours ajouter À LA FIN du container
    container.appendChild(backlogSection);
    
    // Maintenant remplir la liste
    const storiesList = backlogSection.querySelector('.stories-list');
    
    stories.forEach((story, index) => {
        const storyItem = document.createElement('div');
        storyItem.className = `story-item status-${story.status}`;
        storyItem.style.animation = `fadeIn 0.5s ease-out ${index * 0.05}s`;
        storyItem.style.opacity = '0';
        storyItem.style.animationFillMode = 'forwards';
        
        storyItem.innerHTML = `
            <div class="story-item-header">
                <span class="story-item-id">${escapeHtml(story.story_id)}</span>
                <span class="story-item-title">${escapeHtml(story.title)}</span>
            </div>
            <div class="story-item-footer">
                <span class="story-item-priority priority-${story.priority}">
                    ${story.priority.charAt(0).toUpperCase() + story.priority.slice(1)}
                </span>
                ${story.estimation !== null 
                    ? `<span class="story-item-estimation">✓ ${story.estimation} pts</span>`
                    : `<span class="story-item-status">
                        ${story.status === 'voting' ? '⏳ En cours' : 
                          (story.status === 'pending' ? '⏸️ En attente' : '✅ Estimée')}
                       </span>`
                }
            </div>
        `;
        
        storiesList.appendChild(storyItem);
    });
}

/**
 * Réactiver les cartes de vote (après un revote)
 */
function enableVotingCards() {
    document.querySelectorAll('.card-vote').forEach(btn => {
        btn.disabled = false;
        btn.classList.remove('disabled');
    });
    
    const voteConfirmation = document.querySelector('.vote-confirmation');
    if (voteConfirmation) {
        voteConfirmation.style.display = 'none';
    }
    
    showNotification('🔄 Nouveau tour de vote !', 'warning');
}

/**
 * Jouer un son de notification (optionnel)
 */
function playNotificationSound() {
    // Son simple avec Web Audio API
    try {
        const audioContext = new (window.AudioContext || window.webkitAudioContext)();
        const oscillator = audioContext.createOscillator();
        const gainNode = audioContext.createGain();
        
        oscillator.connect(gainNode);
        gainNode.connect(audioContext.destination);
        
        oscillator.frequency.value = 800;
        oscillator.type = 'sine';
        
        gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
        gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.1);
        
        oscillator.start(audioContext.currentTime);
        oscillator.stop(audioContext.currentTime + 0.1);
    } catch (e) {
        // Son non supporté, pas grave
    }
}

/**
 * Obtenir l'ID de la story courante
 */
function getCurrentStoryId() {
    // Chercher dans la story-card
    const storyCard = document.querySelector('.story-card[data-story-id]');
    if (storyCard) {
        return storyCard.dataset.storyId;
    }
    
    // Fallback global si disponible
    if (typeof currentStoryId !== 'undefined') {
        return currentStoryId;
    }
    
    return null;
}

/**
 * Modal d'import
 */
function showImportModal() {
    document.getElementById('import-modal').style.display = 'flex';
}

function closeImportModal() {
    document.getElementById('import-modal').style.display = 'none';
}

function closeRevealModal() {
    const modal = document.getElementById('reveal-modal');
    modal.style.display = 'none';
    // Débloquer le polling automatique
    isModalOpen = false;
}

// Gestion du formulaire d'import
document.getElementById('import-form')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    formData.append('action', 'import_backlog');
    
    try {
        const response = await fetch(API_URL, {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification(`✅ ${data.message}`, 'success');
            closeImportModal();
            setTimeout(() => location.reload(), 1500);
        } else {
            showNotification('❌ ' + data.error, 'error');
        }
    } catch (error) {
        console.error('Erreur:', error);
        showNotification('❌ Erreur lors de l\'import', 'error');
    }
});

// File d'attente pour les notifications
let notificationQueue = [];
let isShowingNotification = false;

/**
 * Vider la file de notifications (optionnel)
 */
function clearNotificationQueue() {
    notificationQueue = [];
}

/**
 * Afficher une notification
 */
function showNotification(message, type = 'info') {
    // Éviter les doublons dans la file
    const isDuplicate = notificationQueue.some(
        n => n.message === message && n.type === type
    );
    
    if (isDuplicate) {
        console.log('Notification en double évitée:', message);
        return;
    }
    
    // Ajouter à la file d'attente
    notificationQueue.push({ message, type });
    
    // Si pas de notification en cours, afficher
    if (!isShowingNotification) {
        displayNextNotification();
    }
}

/**
 * Afficher la prochaine notification de la file
 */
function displayNextNotification() {
    if (notificationQueue.length === 0) {
        isShowingNotification = false;
        return;
    }
    
    isShowingNotification = true;
    const { message, type } = notificationQueue.shift();
    
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.textContent = message;
    
    document.body.appendChild(notification);
    
    // Animation d'entrée
    setTimeout(() => {
        notification.classList.add('show');
    }, 10);
    
    // Retrait après 3 secondes
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => {
            notification.remove();
            // Afficher la prochaine notification
            displayNextNotification();
        }, 300);
    }, 3000);
}

// Fermer les modals en cliquant en dehors
window.addEventListener('click', (e) => {
    const importModal = document.getElementById('import-modal');
    const revealModal = document.getElementById('reveal-modal');
    
    if (e.target === importModal) {
        closeImportModal();
    }
    if (e.target === revealModal) {
        closeRevealModal();
    }
});