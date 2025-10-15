// MetaMask Connection Handler
// This script prevents console errors when MetaMask is not available

(function() {
    'use strict';
    
    // Check if we're in a browser environment
    if (typeof window === 'undefined') return;
    
    // Prevent MetaMask connection errors by wrapping the ethereum object
    if (typeof window.ethereum === 'undefined') {
        // Create a mock ethereum object to prevent errors
        window.ethereum = {
            isMetaMask: false,
            request: function() {
                return Promise.reject(new Error('MetaMask extension not found'));
            },
            on: function() {},
            removeListener: function() {},
            _metamask: {
                isUnlocked: function() { return false; }
            }
        };
        
        console.log('MetaMask not detected - using fallback handler');
    } else {
        // MetaMask is available, but let's add some safety checks
        const originalRequest = window.ethereum.request;
        
        window.ethereum.request = function(args) {
            try {
                return originalRequest.call(this, args);
            } catch (error) {
                console.warn('MetaMask request error:', error.message);
                return Promise.reject(error);
            }
        };
        
        // Add connection state tracking
        window.ethereum._connectionState = {
            isConnecting: false,
            lastError: null
        };
        
        console.log('MetaMask detected and enhanced with error handling');
    }
    
    // Global MetaMask connection function
    window.connectMetaMask = async function() {
        try {
            if (typeof window.ethereum === 'undefined' || !window.ethereum.isMetaMask) {
                throw new Error('MetaMask extension not found');
            }
            
            // Check if already connecting
            if (window.ethereum._connectionState.isConnecting) {
                throw new Error('Connection request already pending');
            }
            
            window.ethereum._connectionState.isConnecting = true;
            window.ethereum._connectionState.lastError = null;
            
            const accounts = await window.ethereum.request({ 
                method: 'eth_requestAccounts' 
            });
            
            window.ethereum._connectionState.isConnecting = false;
            
            if (accounts.length === 0) {
                throw new Error('No accounts found');
            }
            
            return accounts[0];
            
        } catch (error) {
            window.ethereum._connectionState.isConnecting = false;
            window.ethereum._connectionState.lastError = error;
            
            // Handle specific error codes
            if (error.code === 4001) {
                throw new Error('Connection rejected by user');
            } else if (error.code === -32002) {
                throw new Error('Connection request already pending');
            }
            
            throw error;
        }
    };
    
    // Global function to check MetaMask availability
    window.isMetaMaskAvailable = function() {
        return typeof window.ethereum !== 'undefined' && window.ethereum.isMetaMask;
    };
    
    // Global function to get connection status
    window.getMetaMaskStatus = function() {
        if (!window.isMetaMaskAvailable()) {
            return { available: false, connected: false, error: 'MetaMask not installed' };
        }
        
        return {
            available: true,
            connected: window.ethereum._connectionState && !window.ethereum._connectionState.isConnecting,
            isConnecting: window.ethereum._connectionState && window.ethereum._connectionState.isConnecting,
            lastError: window.ethereum._connectionState && window.ethereum._connectionState.lastError
        };
    };
    
})();
