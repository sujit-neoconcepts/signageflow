export const can = (permission) => {
    // Check if we're in a browser environment
    if (typeof window === 'undefined' || !window.permissions) {
        return false;
    }
    
    // Super admin has access to everything
    if (window.permissions.all) {
        return true;
    }
    
    // Check if user has specific permission
    return !!window.permissions[permission];
};