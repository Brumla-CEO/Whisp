/**
 * Pomocné funkce pro správu stavu DM chatů a přátelství.
 */

/**
 * Vrátí true pokud má být zobrazeno tlačítko "Odebrat z přátel"
 * v profilu uživatele. Zobrazuje se pouze u cizích uživatelů,
 * kteří jsou aktuálně přátelé.
 *
 * @param {object} currentUser - přihlášený uživatel (z AuthContext)
 * @param {object} profileUser - uživatel jehož profil se zobrazuje
 * @returns {boolean}
 */
export const canShowUnfriendButton = (currentUser, profileUser) => {
    if (!currentUser || !profileUser) return false;
    if (String(currentUser.id) === String(profileUser.id)) return false;
    if (profileUser.role === 'admin') return false;
    return true;
};

/**
 * Vrátí true pokud má být DM chat zavřen při odebrání přítele.
 *
 * @param {object} selectedUser - aktuálně otevřený chat uživatel
 * @param {string} removedUserId - ID odebraného přítele
 * @returns {boolean}
 */
export const shouldCloseDirectChatOnFriendRemoval = (selectedUser, removedUserId) => {
    if (!selectedUser || !removedUserId) return false;
    if (selectedUser.type === 'group') return false;
    return String(selectedUser.id) === String(removedUserId);
};
