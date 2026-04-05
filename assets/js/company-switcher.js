/**
 * Company Switcher - Reliable company switching across all pages
 */

(function() {
    'use strict';
    
    // Get current page info
    function getCurrentPage() {
        const path = window.location.pathname;
        const filename = path.substring(path.lastIndexOf('/') + 1);
        return filename || 'index.php';
    }
    
    // Get current URL parameters
    function getCurrentParams() {
        const params = new URLSearchParams(window.location.search);
        return params;
    }
    
    // Build new URL with company parameter
    function buildCompanyUrl(companyId) {
        const currentPage = getCurrentPage();
        const params = getCurrentParams();
        
        // Update or add company parameter
        params.set('company', companyId);
        
        // Build new URL
        const newUrl = currentPage + '?' + params.toString();
        return newUrl;
    }
    
    // Main switch company function
    window.switchCompany = function(companyId) {
        if (!companyId) {
            console.error('No company ID provided');
            return;
        }
        
        console.log('Switching company to:', companyId);
        
        // Show a loading indicator if possible (optional)
        const oldCursor = document.body.style.cursor;
        document.body.style.cursor = 'wait';
        
        const rootPath = typeof webRoot !== 'undefined' ? webRoot : '';
        // Update Session via API first to ensure backend state is correct
        fetch(rootPath + '/api/switch-company.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ company_id: companyId })
        })
        .then(response => response.json())
        .then(data => {
            document.body.style.cursor = oldCursor;
            
            if (!data.success) {
                console.error('Failed to switch company session:', data.message);
                alert('Failed to switch company: ' + data.message);
                return;
            }
            
            console.log('Session switched. Updating tabs...');
            
            // Check if we're in a tab system
            const isInTabSystem = document.querySelector('.tab-content-area') !== null;
            
            // Ensure window.tabs is valid array
            const hasTabs = Array.isArray(window.tabs) && window.tabs.length > 0;
            
            if (isInTabSystem && hasTabs) {
                
                // Update ALL tabs
                window.tabs.forEach(tab => {
                    try {
                        const url = new URL(tab.url, window.location.origin);
                        url.searchParams.set('company', companyId);
                        tab.url = url.pathname + url.search; // Update internal URL
                    } catch (e) {
                        console.error('Error updating tab URL:', tab, e);
                    }
                });
                
                // Save updated tabs to storage
                if (typeof window.saveTabs === 'function') {
                    window.saveTabs();
                }
                
                // Reload the active tab immediately
                if (window.activeTabId && typeof window.loadTabContent === 'function') {
                    const activeTab = window.tabs.find(t => t.id === window.activeTabId);
                    if (activeTab) {
                        console.log('Reloading active tab:', activeTab.title);
                        window.loadTabContent(activeTab.id, activeTab.url);
                    } else {
                         // Fallback if active tab not found
                        window.location.reload();
                    }
                } else {
                     // Fallback if activeTabId missing
                    window.location.reload();
                }
            } else {
                // Regular page reload with company parameter
                // Build new URL manually to avoid dependency on helpers
                try {
                    const url = new URL(window.location.href);
                    url.searchParams.set('company', companyId);
                    window.location.href = url.toString();
                } catch(e) {
                    // Fallback for IE/Legacy
                    if (window.location.href.indexOf('?') > -1) {
                        window.location.href = window.location.href + '&company=' + companyId;
                    } else {
                        window.location.href = window.location.href + '?company=' + companyId;
                    }
                }
            }
        })
        .catch(err => {
            document.body.style.cursor = oldCursor;
            console.error('Network error switching company:', err);
            // Fallback: Try client-side reload anyway
            const url = new URL(window.location.href);
            url.searchParams.set('company', companyId);
            window.location.href = url.toString();
        });
    };

    /* 
       Legacy initialization removed. 
       We now rely on the inline onchange="..." in the HTML to call switchCompany().
       This avoids race conditions and DOM replacement issues. 
    */
    console.log('Company switcher loaded. Global function switchCompany() is ready.');
})();
