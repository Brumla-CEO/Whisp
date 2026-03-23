/**
 * Rozhoduje zda zavřít DM chat když byl přítel odebrán.
 * Vrátí true pokud aktuálně otevřený chat patří danému uživateli.
 */
export const shouldCloseDirectChatOnFriendRemoval = (selectedChatUser, removedUserId) => {
    if (!selectedChatUser || !removedUserId) return false;
    if (selectedChatUser.type === 'group') return false;
    return String(selectedChatUser.id) === String(removedUserId);
};

/**
 * Rozhoduje zda zobrazit tlačítko "Odebrat z přátel" v profilu.
 */
export const canShowUnfriendButton = (currentUser, viewingUser) => {
    if (!currentUser || !viewingUser) return false;
    if (String(currentUser.id) === String(viewingUser.id)) return false;
    if (viewingUser.role === 'admin') return false;
    return true;
};