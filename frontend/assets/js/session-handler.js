/**
 * Session Handler Module
 * Manages user authentication state and session data across the application
 */

const SessionHandler = (function () {
  'use strict';

  // Configuration
  const STORAGE_KEY = 'clinic_user_session';
  const SESSION_DURATION = 24 * 60 * 60 * 1000; // 24 hours in milliseconds

  /**
   * Set a cookie
   * @param {string} name - Cookie name
   * @param {string} value - Cookie value
   * @param {number} days - Expiration in days
   */
  function setCookie(name, value, days) {
    const expires = new Date();
    expires.setTime(expires.getTime() + days * 24 * 60 * 60 * 1000);
    document.cookie = name + '=' + encodeURIComponent(value) + ';expires=' + expires.toUTCString() + ';path=/';
  }

  /**
   * Get a cookie value
   * @param {string} name - Cookie name
   * @returns {string|null} Cookie value or null
   */
  function getCookie(name) {
    const nameEQ = name + '=';
    const ca = document.cookie.split(';');
    for (let i = 0; i < ca.length; i++) {
      let c = ca[i];
      while (c.charAt(0) === ' ') c = c.substring(1, c.length);
      if (c.indexOf(nameEQ) === 0) return decodeURIComponent(c.substring(nameEQ.length, c.length));
    }
    return null;
  }

  /**
   * Delete a cookie
   * @param {string} name - Cookie name
   */
  function deleteCookie(name) {
    document.cookie = name + '=;expires=Thu, 01 Jan 1970 00:00:00 UTC;path=/;';
  }

  /**
   * Save user session data
   * @param {Object} userData - User data from login response
   * @param {boolean} rememberMe - Whether to persist session
   */
  function saveSession(userData, rememberMe) {
    const sessionData = {
      user: userData.user,
      notifications: userData.notifications || [],
      timestamp: Date.now(),
      rememberMe: rememberMe
    };

    // Store in localStorage (more secure than cookies for larger data)
    localStorage.setItem(STORAGE_KEY, JSON.stringify(sessionData));

    // Also set a simple auth cookie for quick checks
    const cookieDays = rememberMe ? 30 : 1;
    setCookie('clinic_auth', 'true', cookieDays);
    setCookie('clinic_user_id', userData.user.user_id, cookieDays);
    setCookie('clinic_usertype', userData.user.usertype_id, cookieDays);
  }

  /**
   * Get current session data
   * @returns {Object|null} Session data or null if not found/expired
   */
  function getSession() {
    try {
      const sessionStr = localStorage.getItem(STORAGE_KEY);
      if (!sessionStr) return null;

      const session = JSON.parse(sessionStr);
      
      // Check if session is expired (if not "remember me")
      if (!session.rememberMe) {
        const elapsed = Date.now() - session.timestamp;
        if (elapsed > SESSION_DURATION) {
          clearSession();
          return null;
        }
      }

      return session;
    } catch (e) {
      console.error('Error reading session:', e);
      return null;
    }
  }

  /**
   * Get current user data
   * @returns {Object|null} User object or null
   */
  function getUser() {
    const session = getSession();
    return session ? session.user : null;
  }

  /**
   * Check if user is authenticated
   * @returns {boolean}
   */
  function isAuthenticated() {
    return getCookie('clinic_auth') === 'true' && getSession() !== null;
  }

  /**
   * Get user type ID
   * @returns {number|null}
   */
  function getUserType() {
    const user = getUser();
    return user ? user.usertype_id : null;
  }

  /**
   * Get user full name
   * @returns {string}
   */
  function getUserFullName() {
    const user = getUser();
    if (!user || !user.profile) return 'User';
    
    const firstName = user.profile.first_name || '';
    const lastName = user.profile.last_name || '';
    return (firstName + ' ' + lastName).trim() || user.username || 'User';
  }

  /**
   * Get user role name
   * @returns {string}
   */
  function getUserRole() {
    const userType = getUserType();
    const roles = {
      1: 'Admin',
      2: 'Doctor',
      3: 'Secretary',
      4: 'Receptionist',
      5: 'Patient'
    };
    return roles[userType] || 'User';
  }

  /**
   * Clear session and logout
   */
  function clearSession() {
    localStorage.removeItem(STORAGE_KEY);
    deleteCookie('clinic_auth');
    deleteCookie('clinic_user_id');
    deleteCookie('clinic_usertype');
  }

  /**
   * Logout and redirect to login
   */
  function logout() {
    clearSession();
    window.location.href = 'login.html';
  }

  /**
   * Redirect to appropriate dashboard based on user type
   */
  function redirectToDashboard() {
    const userType = getUserType();
    
    const dashboards = {
      1: 'frontend/admin/dashboard.html',
      2: 'frontend/doctor/dashboard.html',
      3: 'frontend/secretary/dashboard.html',
      4: 'frontend/receptionist/dashboard.html',
      5: 'frontend/patients/dashboard.html'
    };

    const dashboard = dashboards[userType] || 'dashboard.html';
    window.location.href = dashboard;
  }

  /**
   * Require authentication - redirect to login if not authenticated
   * Call this on protected pages
   */
  function requireAuth() {
    if (!isAuthenticated()) {
      window.location.href = '../login.html';
      return false;
    }
    return true;
  }


  function requireUserType(allowedTypes) {
    if (!requireAuth()) return false;

    const userType = getUserType();
    const allowed = Array.isArray(allowedTypes) ? allowedTypes : [allowedTypes];

    if (!allowed.includes(userType)) {
      alert('You do not have permission to access this page.');
      redirectToDashboard();
      return false;
    }
    return true;
  }

  /**
   * Update unread notifications count in session
   * @param {Array} notifications - New notifications array
   */
  function updateNotifications(notifications) {
    const session = getSession();
    if (session) {
      session.notifications = notifications;
      localStorage.setItem(STORAGE_KEY, JSON.stringify(session));
    }
  }

  /**
   * Get unread notifications count
   * @returns {number}
   */
  function getUnreadNotificationsCount() {
    const session = getSession();
    if (!session || !session.notifications) return 0;
    return session.notifications.filter(n => !n.is_read).length;
  }

  // Public API
  return {
    saveSession: saveSession,
    getSession: getSession,
    getUser: getUser,
    getUserType: getUserType,
    getUserFullName: getUserFullName,
    getUserRole: getUserRole,
    isAuthenticated: isAuthenticated,
    clearSession: clearSession,
    logout: logout,
    redirectToDashboard: redirectToDashboard,
    requireAuth: requireAuth,
    requireUserType: requireUserType,
    updateNotifications: updateNotifications,
    getUnreadNotificationsCount: getUnreadNotificationsCount
  };
})();

// Make available globally
window.SessionHandler = SessionHandler;
