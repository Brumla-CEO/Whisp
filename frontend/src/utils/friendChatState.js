export function canShowUnfriendButton(currentUser, viewedUser) {
    if (!currentUser || !viewedUser) return false;

    const isOwnProfile =
        (currentUser.id && viewedUser.id && String(currentUser.id) === String(viewedUser.id)) ||
        (currentUser.username && viewedUser.username && currentUser.username === viewedUser.username) ||
        (currentUser.email && viewedUser.email && currentUser.email === viewedUser.email);

    return !isOwnProfile;
}

export function shouldCloseDirectChatOnFriendRemoval(selectedChatUser, removedUserId) {
    if (!selectedChatUser || !removedUserId) return false;
    if (selectedChatUser.type === 'group') return false;
    return String(selectedChatUser.id) === String(removedUserId);
}
