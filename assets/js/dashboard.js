// Dashboard JavaScript
lucide.createIcons();

// Global variables
let currentUser = null;
let token = null;
let links = [];
let bioPages = [];
let selectedTheme = 'minimal';
let bioLinks = [];
let editingBioId = null;

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    checkAuth();
    initializeEventListeners();
});

// Check authentication
function checkAuth() {
    token = localStorage.getItem('token');
    const userStr = localStorage.getItem('user');

    if (!token || !userStr) {
        window.location.href = '/login.html';
        return;
    }

    currentUser = JSON.parse(userStr);
    updateUI();
    loadData();
}

// Initialize event listeners
function initializeEventListeners() {
    // Logout
    document.getElementById('logoutBtn').addEventListener('click', logout);

    // Tabs
    document.getElementById('linksTab').addEventListener('click', () => switchTab('links'));
    document.getElementById('bioTab').addEventListener('click', () => switchTab('bio'));

    // Links
    document.getElementById('createLinkBtn').addEventListener('click', () => {
        document.getElementById('createLinkForm').classList.remove('hidden');
    });
    document.getElementById('cancelLinkBtn').addEventListener('click', () => {
        document.getElementById('createLinkForm').classList.add('hidden');
        document.getElementById('linkForm').reset();
    });
    document.getElementById('linkForm').addEventListener('submit', createLink);

    // Bio
    document.getElementById('createBioBtn').addEventListener('click', () => {
        editingBioId = null;
        document.getElementById('bioForm').classList.remove('hidden');
        resetBioForm();
    });
    document.getElementById('cancelBioBtn').addEventListener('click', () => {
        document.getElementById('bioForm').classList.add('hidden');
        editingBioId = null;
    });
    document.getElementById('bioPageForm').addEventListener('submit', saveBioPage);
    document.getElementById('addBioLinkBtn').addEventListener('click', addBioLink);

    // Theme selection
    document.querySelectorAll('.theme-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            document.querySelectorAll('.theme-btn').forEach(b => b.classList.remove('border-indigo-600'));
            btn.classList.add('border-indigo-600');
            selectedTheme = btn.dataset.theme;
            updateBioPreview();
        });
    });

    // Bio form inputs
    document.getElementById('bioTitle').addEventListener('input', updateBioPreview);
    document.getElementById('bioDescription').addEventListener('input', updateBioPreview);
    document.getElementById('bioProfileImage').addEventListener('input', updateBioPreview);

    // QR Modal
    document.getElementById('closeQrModal').addEventListener('click', closeQrModal);
}

// Update UI with user info
function updateUI() {
    document.getElementById('userInfo').textContent = `${currentUser.username} • ${currentUser.plan === 'premium' ? 'Premium' : 'Ücretsiz'}`;
    document.getElementById('userPlan').textContent = `${currentUser.username} • ${currentUser.plan === 'premium' ? 'Premium Plan' : 'Ücretsiz Plan'}`;
    
    if (currentUser.plan === 'free') {
        document.getElementById('upgradBtn').classList.remove('hidden');
    }
}

// Load data
async function loadData() {
    await loadLinks();
    await loadBioPages();
}

// Load links
async function loadLinks() {
    try {
        const response = await fetch('/api/link.php', {
            headers: {
                'Authorization': `Bearer ${token}`
            }
        });

        const data = await response.json();
        
        if (data.success) {
            links = data.data;
            renderLinks();
        }
    } catch (error) {
        console.error('Error loading links:', error);
    }
}

// Load bio pages
async function loadBioPages() {
    try {
        const response = await fetch('/api/bio.php', {
            headers: {
                'Authorization': `Bearer ${token}`
            }
        });

        const data = await response.json();
        
        if (data.success) {
            bioPages = data.data;
            renderBioPages();
        }
    } catch (error) {
        console.error('Error loading bio pages:', error);
    }
}

// Render links
function renderLinks() {
    const container = document.getElementById('linksList');
    
    if (links.length === 0) {
        container.innerHTML = `
            <div class="bg-white rounded-xl p-12 text-center">
                <i data-lucide="link" class="w-16 h-16 mx-auto text-gray-300 mb-4"></i>
                <p class="text-gray-600">Henüz kısa link oluşturmadınız</p>
            </div>
        `;
        lucide.createIcons();
        return;
    }

    container.innerHTML = links.map(link => `
        <div class="bg-white rounded-xl shadow-md p-6">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center">
                <div class="flex-1 mb-4 md:mb-0">
                    <div class="flex items-center space-x-2 mb-2">
                        <h3 class="font-semibold text-lg">${link.shortUrl}</h3>
                        <button onclick="copyToClipboard('${link.shortUrl}')" class="text-indigo-600 hover:text-indigo-700">
                            <i data-lucide="copy" class="w-4 h-4"></i>
                        </button>
                    </div>
                    <p class="text-gray-600 text-sm truncate max-w-md">${link.originalUrl}</p>
                    <div class="flex items-center mt-2 text-sm text-gray-500">
                        <i data-lucide="bar-chart-3" class="w-4 h-4 mr-1"></i>
                        ${link.clicks} tıklama
                    </div>
                </div>
                <div class="flex space-x-2">
                    <button onclick="showQRCode('${link.shortUrl}')" class="p-2 text-gray-600 hover:bg-gray-100 rounded-lg" title="QR Kod">
                        <i data-lucide="qr-code" class="w-5 h-5"></i>
                    </button>
                    <button onclick="deleteLink(${link.id})" class="p-2 text-red-600 hover:bg-red-50 rounded-lg">
                        <i data-lucide="trash-2" class="w-5 h-5"></i>
                    </button>
                </div>
            </div>
        </div>
    `).join('');
    
    lucide.createIcons();
}

// Render bio pages
function renderBioPages() {
    const container = document.getElementById('bioPagesList');
    
    if (bioPages.length === 0) {
        container.innerHTML = `
            <div class="bg-white rounded-xl p-12 text-center">
                <i data-lucide="user" class="w-16 h-16 mx-auto text-gray-300 mb-4"></i>
                <p class="text-gray-600">Henüz bio sayfası oluşturmadınız</p>
            </div>
        `;
        lucide.createIcons();
        return;
    }

    container.innerHTML = bioPages.map(bio => `
        <div class="bg-white rounded-xl shadow-md p-6">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center">
                <div class="flex-1 mb-4 md:mb-0">
                    <div class="flex items-center space-x-2 mb-2">
                        <h3 class="font-semibold text-lg">${bio.title}</h3>
                        <button onclick="copyToClipboard('${bio.url}')" class="text-indigo-600 hover:text-indigo-700">
                            <i data-lucide="copy" class="w-4 h-4"></i>
                        </button>
                    </div>
                    <p class="text-gray-600 text-sm mb-2">${bio.url}</p>
                    <p class="text-gray-500 text-sm">${bio.description || ''}</p>
                </div>
                <div class="flex space-x-2">
                    <a href="${bio.url}" target="_blank" class="p-2 text-indigo-600 hover:bg-indigo-50 rounded-lg" title="Görüntüle">
                        <i data-lucide="external-link" class="w-5 h-5"></i>
                    </a>
                    <button onclick="editBioPage(${bio.id})" class="p-2 text-gray-600 hover:bg-gray-100 rounded-lg">
                        <i data-lucide="edit" class="w-5 h-5"></i>
                    </button>
                    <button onclick="deleteBioPage(${bio.id})" class="p-2 text-red-600 hover:bg-red-50 rounded-lg">
                        <i data-lucide="trash-2" class="w-5 h-5"></i>
                    </button>
                </div>
            </div>
        </div>
    `).join('');
    
    lucide.createIcons();
}

// Create link
async function createLink(e) {
    e.preventDefault();
    
    const originalUrl = document.getElementById('originalUrl').value;
    
    try {
        const response = await fetch('/api/link.php', {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ originalUrl })
        });

        const data = await response.json();
        
        if (data.success) {
            showNotification('Link başarıyla oluşturuldu!');
            document.getElementById('createLinkForm').classList.add('hidden');
            document.getElementById('linkForm').reset();
            await loadLinks();
        } else {
            showNotification(data.message, true);
        }
    } catch (error) {
        console.error('Error creating link:', error);
        showNotification('Bir hata oluştu', true);
    }
}

// Delete link
async function deleteLink(id) {
    if (!confirm('Bu linki silmek istediğinizden emin misiniz?')) return;
    
    try {
        const response = await fetch(`/api/link.php?id=${id}`, {
            method: 'DELETE',
            headers: {
                'Authorization': `Bearer ${token}`
            }
        });

        const data = await response.json();
        
        if (data.success) {
            showNotification('Link silindi');
            await loadLinks();
        }
    } catch (error) {
        console.error('Error deleting link:', error);
    }
}

// Save bio page
async function saveBioPage(e) {
    e.preventDefault();
    
    const bioData = {
        username: currentUser.username,
        title: document.getElementById('bioTitle').value,
        description: document.getElementById('bioDescription').value,
        profileImage: document.getElementById('bioProfileImage').value,
        links: bioLinks,
        theme: selectedTheme
    };

    try {
        const url = editingBioId ? `/api/bio.php?id=${editingBioId}` : '/api/bio.php';
        const method = editingBioId ? 'PUT' : 'POST';

        const response = await fetch(url, {
            method,
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(bioData)
        });

        const data = await response.json();
        
        if (data.success) {
            showNotification(editingBioId ? 'Bio sayfası güncellendi!' : 'Bio sayfası oluşturuldu!');
            document.getElementById('bioForm').classList.add('hidden');
            editingBioId = null;
            await loadBioPages();
        } else {
            showNotification(data.message, true);
        }
    } catch (error) {
        console.error('Error saving bio page:', error);
        showNotification('Bir hata oluştu', true);
    }
}

// Edit bio page
function editBioPage(id) {
    const bio = bioPages.find(b => b.id === id);
    if (!bio) return;

    editingBioId = id;
    bioLinks = bio.links || [];
    selectedTheme = bio.theme || 'minimal';

    document.getElementById('bioTitle').value = bio.title;
    document.getElementById('bioDescription').value = bio.description || '';
    document.getElementById('bioProfileImage').value = bio.profileImage || '';

    // Select theme
    document.querySelectorAll('.theme-btn').forEach(btn => {
        btn.classList.toggle('border-indigo-600', btn.dataset.theme === selectedTheme);
    });

    document.getElementById('bioForm').classList.remove('hidden');
    renderBioLinks();
    updateBioPreview();
}

// Delete bio page
async function deleteBioPage(id) {
    if (!confirm('Bu bio sayfasını silmek istediğinizden emin misiniz?')) return;
    
    try {
        const response = await fetch(`/api/bio.php?id=${id}`, {
            method: 'DELETE',
            headers: {
                'Authorization': `Bearer ${token}`
            }
        });

        const data = await response.json();
        
        if (data.success) {
            showNotification('Bio sayfası silindi');
            await loadBioPages();
        }
    } catch (error) {
        console.error('Error deleting bio page:', error);
    }
}

// Add bio link
function addBioLink() {
    bioLinks.push({ title: '', url: '' });
    renderBioLinks();
}

// Render bio links
function renderBioLinks() {
    const container = document.getElementById('bioLinksList');
    container.innerHTML = bioLinks.map((link, index) => `
        <div class="flex space-x-2">
            <input 
                type="text"
                value="${link.title}"
                onchange="updateBioLink(${index}, 'title', this.value)"
                class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm"
                placeholder="Link Başlığı"
            />
            <input 
                type="url"
                value="${link.url}"
                onchange="updateBioLink(${index}, 'url', this.value)"
                class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm"
                placeholder="Link URL"
            />
            <button 
                type="button"
                onclick="removeBioLink(${index})"
                class="p-2 text-red-600 hover:bg-red-50 rounded-lg"
            >
                <i data-lucide="trash-2" class="w-4 h-4"></i>
            </button>
        </div>
    `).join('');
    lucide.createIcons();
}

// Update bio link
function updateBioLink(index, field, value) {
    bioLinks[index][field] = value;
    updateBioPreview();
}

// Remove bio link
function removeBioLink(index) {
    bioLinks.splice(index, 1);
    renderBioLinks();
    updateBioPreview();
}

// Update bio preview
function updateBioPreview() {
    const title = document.getElementById('bioTitle').value || 'Your Title';
    const description = document.getElementById('bioDescription').value || 'Your description';
    const profileImage = document.getElementById('bioProfileImage').value;

    const themeStyles = {
        minimal: 'bg-white text-gray-900',
        gradient: 'bg-gradient-to-br from-purple-400 to-pink-400 text-white',
        dark: 'bg-gray-900 text-white',
        colorful: 'bg-gradient-to-br from-yellow-400 via-red-500 to-pink-500 text-white'
    };

    const linkStyles = {
        minimal: 'bg-gray-100 text-gray-900',
        gradient: 'bg-white bg-opacity-20',
        dark: 'bg-gray-800',
        colorful: 'bg-white bg-opacity-20'
    };

    const preview = document.getElementById('bioPreview');
    preview.className = `${themeStyles[selectedTheme]} rounded-xl p-8 min-h-96`;
    preview.innerHTML = `
        <div class="text-center mb-6">
            ${profileImage ? `<img src="${profileImage}" alt="Profile" class="w-24 h-24 rounded-full mx-auto mb-4 object-cover" onerror="this.style.display='none'">` : ''}
            <h2 class="text-2xl font-bold mb-2">${title}</h2>
            <p class="opacity-90">${description}</p>
        </div>
        <div class="space-y-3">
            ${bioLinks.map((link, index) => `
                <div class="${linkStyles[selectedTheme]} p-4 rounded-lg text-center font-medium">
                    ${link.title || `Link ${index + 1}`}
                </div>
            `).join('')}
        </div>
    `;
}

// Reset bio form
function resetBioForm() {
    document.getElementById('bioPageForm').reset();
    bioLinks = [];
    selectedTheme = 'minimal';
    document.querySelectorAll('.theme-btn').forEach(btn => {
        btn.classList.toggle('border-indigo-600', btn.dataset.theme === 'minimal');
    });
    renderBioLinks();
    updateBioPreview();
}

// Show QR code
function showQRCode(url) {
    const qrContainer = document.getElementById('qrcode');
    qrContainer.innerHTML = '';
    
    new QRCode(qrContainer, {
        text: `https://${url}`,
        width: 256,
        height: 256
    });

    document.getElementById('qrModal').classList.remove('hidden');
    document.getElementById('qrModal').classList.add('flex');
    lucide.createIcons();
}

// Close QR modal
function closeQrModal() {
    document.getElementById('qrModal').classList.add('hidden');
    document.getElementById('qrModal').classList.remove('flex');
}

// Switch tab
function switchTab(tab) {
    if (tab === 'links') {
        document.getElementById('linksTab').className = 'pb-3 px-4 font-semibold border-b-2 border-indigo-600 text-indigo-600';
        document.getElementById('bioTab').className = 'pb-3 px-4 font-semibold text-gray-600 hover:text-indigo-600';
        document.getElementById('linksSection').classList.remove('hidden');
        document.getElementById('bioSection').classList.add('hidden');
    } else {
        document.getElementById('bioTab').className = 'pb-3 px-4 font-semibold border-b-2 border-indigo-600 text-indigo-600';
        document.getElementById('linksTab').className = 'pb-3 px-4 font-semibold text-gray-600 hover:text-indigo-600';
        document.getElementById('bioSection').classList.remove('hidden');
        document.getElementById('linksSection').classList.add('hidden');
    }
}

// Copy to clipboard
function copyToClipboard(text) {
    navigator.clipboard.writeText(`https://${text}`).then(() => {
        showNotification('Kopyalandı!');
    });
}

// Show notification
function showNotification(message, isError = false) {
    const notification = document.getElementById('notification');
    const notificationMessage = document.getElementById('notificationMessage');
    
    notificationMessage.textContent = message;
    notification.className = `fixed top-4 right-4 px-6 py-3 rounded-lg shadow-lg z-50 ${isError ? 'bg-red-500' : 'bg-green-500'} text-white`;
    notification.classList.remove('hidden');
    
    setTimeout(() => {
        notification.classList.add('hidden');
    }, 3000);
}

// Logout
function logout() {
    localStorage.removeItem('token');
    localStorage.removeItem('user');
    window.location.href = '/login.html';
}
