/**
 * Tab Management System with Persistence
 */

// Global state
window.tabs = [];
window.activeTabId = null;
window.tabIdCounter = 0;

// Save tabs to localStorage
function saveTabs() {
    try {
        const tabsData = {
            tabs: window.tabs.map(tab => ({
                id: tab.id,
                url: tab.url,
                title: tab.title
            })),
            activeTabId: window.activeTabId,
            tabIdCounter: window.tabIdCounter
        };
        localStorage.setItem('cashflow_tabs', JSON.stringify(tabsData));
    } catch (e) {
        console.error('Failed to save tabs:', e);
    }
}

// Restore tabs from localStorage
function restoreTabs() {
    try {
        const saved = localStorage.getItem('cashflow_tabs');
        if (saved) {
            const tabsData = JSON.parse(saved);
            window.tabs = tabsData.tabs || [];
            window.activeTabId = tabsData.activeTabId;
            window.tabIdCounter = tabsData.tabIdCounter || 0;
            
            // Restore each tab
            if (window.tabs.length > 0) {
                // Filter out invalid tabs
                window.tabs = window.tabs.filter(tab => {
                    // Check for invalid URLs (e.g. titles saved as URLs)
                    if (!tab.url || tab.url.includes('Create%20User') || tab.url.indexOf('.php') === -1 && tab.url.indexOf('/') === -1) {
                         console.warn('Removing invalid tab:', tab);
                         return false;
                    }
                    return true;
                });
                
                renderTabs();
                window.tabs.forEach(tab => {
                    loadTabContent(tab.id, tab.url, false);
                });
                if (window.activeTabId) {
                    // Start validation - check if activeTabId still exists after filtering
                    const activeExists = window.tabs.some(t => t.id === window.activeTabId);
                    if (activeExists) {
                        switchToTab(window.activeTabId);
                    } else if (window.tabs.length > 0) {
                        switchToTab(window.tabs[0].id);
                    }
                }
                return true;
            }
        }
    } catch (e) {
        console.error('Failed to restore tabs:', e);
    }
    return false;
}

function generateTabId() {
    return 'tab-' + (++window.tabIdCounter);
}

// Normalize URL for comparison (remove trailing slashes, normalize query params)
function normalizeUrl(url) {
    try {
        // Handle relative URLs
        if (url.startsWith('/')) {
            url = window.location.origin + url;
        }
        
        const urlObj = new URL(url);
        
        // Sort query parameters for consistent comparison
        const params = Array.from(urlObj.searchParams.entries()).sort((a, b) => a[0].localeCompare(b[0]));
        urlObj.search = '';
        params.forEach(([key, value]) => urlObj.searchParams.append(key, value));
        
        // Remove trailing slash from pathname
        urlObj.pathname = urlObj.pathname.replace(/\/$/, '') || '/';
        
        return urlObj.href;
    } catch (e) {
        // If URL parsing fails, return original
        return url;
    }
}

function openNewTab(url, title, isInitial = false) {
    // Basic validation
    if (!url || (url.indexOf('.php') === -1 && url.indexOf('/') === -1 && !isInitial)) {
        console.error('Invalid URL passed to openNewTab:', url);
        return;
    }

    // Normalize URL for comparison
    const normalizedUrl = normalizeUrl(url);
    
    // Check if tab already exists with normalized URL comparison
    const existingTab = tabs.find(tab => normalizeUrl(tab.url) === normalizedUrl);
    if (existingTab) {
        switchToTab(existingTab.id);
        console.log('✓ Tab already exists, switching to:', existingTab.title);
        
        // Show subtle notification if notification system is available
        if (typeof showInfo === 'function') {
            showInfo('Tab "' + existingTab.title + '" is already open', 2000);
        }
        return;
    }
    
    console.log('✓ Opening new tab:', title);
    
    const tabId = generateTabId();
    const tab = {
        id: tabId,
        url: url,
        title: title
    };
    
    window.tabs.push(tab);
    saveTabs(); // Save after adding tab
    renderTabs();
    loadTabContent(tabId, url, isInitial);
    switchToTab(tabId);
}

function renderTabs() {
    const container = document.getElementById('tabContainer');
    container.innerHTML = '';
    
    window.tabs.forEach(tab => {
        const tabEl = document.createElement('div');
        tabEl.className = 'tab' + (tab.id === window.activeTabId ? ' active' : '');
        tabEl.setAttribute('data-tab-id', tab.id);
        tabEl.onclick = () => switchToTab(tab.id);
        
        const titleSpan = document.createElement('span');
        titleSpan.className = 'tab-title';
        titleSpan.textContent = tab.title;
        
        const closeBtn = document.createElement('button');
        closeBtn.className = 'tab-close';
        closeBtn.innerHTML = '×';
        closeBtn.onclick = (e) => {
            e.stopPropagation();
            closeTab(tab.id);
        };
        
        tabEl.appendChild(titleSpan);
        if (window.tabs.length > 1) {
            tabEl.appendChild(closeBtn);
        }
        
        container.appendChild(tabEl);
    });
}

function loadTabContent(tabId, url, isInitial = false) {
    const contentArea = document.getElementById('tabContentArea');
    
    // Create content panel
    const panel = document.createElement('div');
    panel.className = 'tab-panel';
    panel.id = 'panel-' + tabId;
    panel.style.display = 'none';
    
    if (isInitial) {
        // For initial load, grab existing content from the page
        const existingContent = document.querySelector('#initialContent .content-wrapper');
        if (existingContent && existingContent.innerHTML.trim()) {
            const wrapper = document.createElement('div');
            wrapper.className = 'container';
            wrapper.style.display = 'block';
            wrapper.innerHTML = '<div class="content-wrapper">' + existingContent.innerHTML + '</div>';
            panel.appendChild(wrapper);
            
            // Hide the original content
            const initialContainer = document.getElementById('initialContent');
            if (initialContainer) {
                initialContainer.style.display = 'none';
            }
        } else {
            // Fallback: load via AJAX if no initial content
            panel.innerHTML = '<div class="tab-loading"><div class="loading-spinner"></div><div class="loading-text">Loading...</div></div>';
            fetch(url)
                .then(response => response.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const content = doc.querySelector('.content-wrapper') || doc.querySelector('.container');
                    
                    if (content) {
                        const wrapper = document.createElement('div');
                        wrapper.className = 'container';
                        wrapper.style.display = 'block';
                        wrapper.innerHTML = '<div class="content-wrapper">' + content.innerHTML + '</div>';
                        panel.innerHTML = '';
                        panel.appendChild(wrapper);
                    }
                });
        }
        contentArea.appendChild(panel);
    } else {
        // Show loading
        panel.innerHTML = '<div class="tab-loading"><div class="loading-spinner"></div><div class="loading-text">Loading...</div></div>';
        contentArea.appendChild(panel);
        
        // Load content via AJAX
        fetch(url)
            .then(response => response.text())
            .then(html => {
                // Extract content between main tags
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                
                // Try different content selectors
                let content = doc.querySelector('.content-wrapper') || 
                             doc.querySelector('.container') ||
                             doc.querySelector('main') ||
                             doc.querySelector('body > *:not(script):not(style)');
                
                // For full-page layouts (like POS), get the body content
                if (!content || content.tagName === 'BODY') {
                    content = doc.body;
                }
                
                if (content) {
                    const wrapper = document.createElement('div');
                    wrapper.className = 'container';
                    wrapper.style.display = 'block';
                    
                    // If it's a full body, clone the structure
                    if (content.tagName === 'BODY') {
                        wrapper.innerHTML = content.innerHTML;
                        // Remove duplicate headers/sidebars from loaded content
                        wrapper.querySelectorAll('.sidebar, .app-wrapper > aside, .mobile-menu-toggle, .mobile-overlay').forEach(el => el.remove());
                    } else {
                        wrapper.innerHTML = '<div class="content-wrapper">' + content.innerHTML + '</div>';
                    }
                    
                    panel.innerHTML = '';
                    panel.appendChild(wrapper);
                    
                        // Execute scripts in loaded content
                        const scripts = panel.querySelectorAll('script');
                        scripts.forEach(script => {
                            try {
                                if (script.src) {
                                    // Remove query parameters for deduplication purposes
                                    const srcUrl = script.src.split('?')[0];
                                    
                                    // Check if script is already loaded by checking normalized src
                                    const existingScripts = document.querySelectorAll('script');
                                    let alreadyLoaded = false;
                                    
                                    for (let i = 0; i < existingScripts.length; i++) {
                                        if (existingScripts[i].src && existingScripts[i].src.split('?')[0] === srcUrl) {
                                            alreadyLoaded = true;
                                            break;
                                        }
                                    }
                                    
                                    if (!alreadyLoaded) {
                                        const newScript = document.createElement('script');
                                        newScript.src = script.src;
                                        document.body.appendChild(newScript);
                                    }
                                } else if (script.textContent.trim()) {
                                // Inline scripts - wrap in IIFE to create new scope and avoid "already declared" errors
                                const scriptContent = script.textContent;
                                
                                // Check if script already has IIFE wrapper
                                const hasIIFE = /^\s*\(function\s*\(/.test(scriptContent) || 
                                               /^\s*\(\s*\(\s*\)\s*=>\s*\{/.test(scriptContent);
                                
                                const wrappedScript = document.createElement('script');
                                if (hasIIFE) {
                                    // Already wrapped, execute as-is
                                    wrappedScript.textContent = scriptContent;
                                } else {
                                    // Wrap in IIFE to prevent variable conflicts
                                    wrappedScript.textContent = `(function() { 
                                        try { 
                                            ${scriptContent} 
                                        } catch(e) { 
                                            console.warn('Tab script error:', e); 
                                        } 
                                    })();`;
                                }
                                document.body.appendChild(wrappedScript);
                                document.body.removeChild(wrappedScript);
                            }
                        } catch (e) {
                            console.warn('Failed to execute tab script:', e);
                        }
                    });
                } else {
                    panel.innerHTML = '<div class="tab-error"><div class="tab-error-icon">⚠</div><div class="tab-error-message">Failed to load content</div></div>';
                }
            })
            .catch(error => {
                panel.innerHTML = '<div class="tab-error"><div class="tab-error-icon">⚠</div><div class="tab-error-message">Error loading content: ' + error.message + '</div></div>';
            });
    }
}

function switchToTab(tabId) {
    window.activeTabId = tabId;
    saveTabs(); // Save active tab state
    
    // Update tab styling
    document.querySelectorAll('.tab').forEach(tab => {
        tab.classList.remove('active');
    });
    const activeTab = document.querySelector(`[data-tab-id="${tabId}"]`);
    if (activeTab) {
        activeTab.classList.add('active');
        
        // Add subtle pulse animation to indicate tab switch
        activeTab.style.animation = 'none';
        setTimeout(() => {
            activeTab.style.animation = 'tabPulse 0.3s ease-out';
        }, 10);
    }
    
    // Show corresponding panel
    document.querySelectorAll('.tab-panel').forEach(panel => {
        panel.style.display = 'none';
    });
    const activePanel = document.getElementById('panel-' + tabId);
    if (activePanel) {
        activePanel.style.display = 'block';
    }
    
    // Update URL without reload
    const tab = window.tabs.find(t => t.id === tabId);
    if (tab) {
        history.pushState({tabId: tabId}, tab.title, tab.url);
        updateSidebarActiveState(tab.url);
    }
}

function updateSidebarActiveState(tabUrl) {
    // Remove active class from all sidebar links
    document.querySelectorAll('.sidebar-menu-link').forEach(link => {
        link.classList.remove('active');
    });

    if (!tabUrl) return;

    try {
        // Get path from tab URL
        const tabUrlObj = new URL(tabUrl, window.location.origin);
        const tabPath = tabUrlObj.pathname;

        // Find sidebar link that matches this path
        const sidebarLinks = document.querySelectorAll('.sidebar-menu-link');
        
        sidebarLinks.forEach(link => {
            const href = link.getAttribute('href');
            if (!href) return;
            
            try {
                const linkUrlObj = new URL(href, window.location.origin);
                // Compare pathnames
                if (linkUrlObj.pathname === tabPath) {
                    link.classList.add('active');
                }
            } catch (e) {
                // Fallback for relative paths
                if (href.indexOf(tabPath) !== -1 || tabPath.indexOf(href.split('?')[0]) !== -1) {
                     link.classList.add('active');
                }
            }
        });
    } catch (e) {
        console.error('Error updating sidebar state:', e);
    }
}

function closeTab(tabId) {
    const index = window.tabs.findIndex(tab => tab.id === tabId);
    if (index === -1) return;
    
    // Remove tab
    window.tabs.splice(index, 1);
    
    // Remove panel
    const panel = document.getElementById('panel-' + tabId);
    if (panel) {
        panel.remove();
    }
    
    // If closing active tab, switch to another
    if (tabId === window.activeTabId && window.tabs.length > 0) {
        const newActiveIndex = Math.max(0, index - 1);
        switchToTab(window.tabs[newActiveIndex].id);
    }
    
    // If no tabs left, open dashboard
    const rootPath = typeof webRoot !== 'undefined' ? webRoot : '';
    if (window.tabs.length === 0) {
        openNewTab(rootPath + '/index.php', 'Dashboard');
    } else {
        saveTabs(); // Save after closing tab
    }
    
    renderTabs();
}

// NOTE: switchCompany is now handled by assets/js/company-switcher.js
// but we keep this here as legacy just in case, updating it to use window.tabs

if (typeof window.switchCompany === 'undefined') {
    window.switchCompany = function(companyId) {
        console.log('legacy switchCompany called');
         // Update all tab URLs with new company parameter
         window.tabs.forEach(tab => {
            try {
                const url = new URL(tab.url, window.location.origin);
                url.searchParams.set('company', companyId);
                tab.url = url.pathname + url.search;
            } catch (e) {
                // If URL construction fails, just append/update company param
                if (tab.url.includes('?')) {
                    if (tab.url.includes('company=')) {
                        tab.url = tab.url.replace(/company=\d+/, 'company=' + companyId);
                    } else {
                        tab.url += '&company=' + companyId;
                    }
                } else {
                    tab.url += '?company=' + companyId;
                }
            }
        });
        saveTabs();
        const rootPath = typeof webRoot !== 'undefined' ? webRoot : '';
        window.location.href = rootPath + '/index.php?company=' + companyId;
    }
}

// Expose functions globally
window.openNewTab = openNewTab;
window.switchToTab = switchToTab;
window.closeTab = closeTab;
window.loadTabContent = loadTabContent;
window.saveTabs = saveTabs;
window.renderTabs = renderTabs;
    
// Intercept sidebar clicks to open in tabs
document.addEventListener('DOMContentLoaded', function() {
    // Try to restore tabs from localStorage
    const restored = restoreTabs();
    
    // If no tabs were restored and no initial tab exists, create one with current page
    if (!restored && window.tabs.length === 0) {
        const currentPath = window.location.pathname + window.location.search;
        const pageTitle = document.querySelector('.dashboard-welcome h2')?.textContent || 
                         document.querySelector('h1')?.textContent || 
                         'Dashboard';
        openNewTab(currentPath, pageTitle, true);
    }
    
    document.querySelectorAll('.sidebar-menu-link').forEach(link => {
        link.addEventListener('click', function(e) {
            // Skip links with target="_blank" (like POS links)
            if (this.getAttribute('target') === '_blank') {
                return; // Let browser handle it normally
            }
            
            e.preventDefault();
            const url = this.getAttribute('href');
            const title = this.textContent.trim();
            openNewTab(url, title);
        });
    });
});

// Handle browser back/forward
window.addEventListener('popstate', function(event) {
    if (event.state && event.state.tabId) {
        switchToTab(event.state.tabId);
    }
});
