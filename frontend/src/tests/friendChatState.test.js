import test from 'node:test';
import assert from 'node:assert/strict';
import { canShowUnfriendButton, shouldCloseDirectChatOnFriendRemoval } from '../utils/friendChatState.js';

test('canShowUnfriendButton hides action on own profile', () => {
    assert.equal(canShowUnfriendButton({ id: '1', username: 'alice' }, { id: '1', username: 'alice' }), false);
    assert.equal(canShowUnfriendButton({ id: '1', username: 'alice' }, { id: '2', username: 'bob' }), true);
});

test('shouldCloseDirectChatOnFriendRemoval closes only matching DM', () => {
    assert.equal(shouldCloseDirectChatOnFriendRemoval({ id: '10', type: 'dm' }, '10'), true);
    assert.equal(shouldCloseDirectChatOnFriendRemoval({ id: '10', type: 'dm' }, '11'), false);
    assert.equal(shouldCloseDirectChatOnFriendRemoval({ id: '10', type: 'group' }, '10'), false);
});
