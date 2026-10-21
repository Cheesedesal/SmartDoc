import './bootstrap';
import './modules';

document.querySelectorAll('[data-notification-center]').forEach((center) => {
    const toggle = center.querySelector('[data-notification-toggle]');
    const panel = center.querySelector('[data-notification-panel]');
    const count = center.querySelector('[data-notification-count]');
    const items = center.querySelector('[data-notification-items]');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

    const loadNotifications = async () => {
        const response = await fetch(center.dataset.endpoint, { headers: { Accept: 'application/json' } });
        if (!response.ok) throw new Error('Notifications could not be loaded.');
        const data = await response.json();
        count.removeAttribute('aria-label');
        count.textContent = data.unread_count > 99 ? '99+' : data.unread_count;
        count.hidden = data.unread_count === 0;
        items.replaceChildren();

        if (!data.notifications.length) {
            const empty = document.createElement('p');
            empty.className = 'muted-copy';
            empty.textContent = 'You have no notifications yet.';
            items.append(empty);
            return;
        }

        data.notifications.forEach((notification) => {
            const link = document.createElement('a');
            link.className = `notification-item${notification.read ? '' : ' is-unread'}`;
            link.href = notification.url;
            const title = document.createElement('strong');
            title.textContent = notification.title;
            const message = document.createElement('span');
            message.textContent = notification.message;
            const date = document.createElement('small');
            date.textContent = notification.created_at;
            link.append(title, message, date);
            link.addEventListener('click', async (event) => {
                if (!notification.read) {
                    event.preventDefault();
                    await fetch(`/notifications/${encodeURIComponent(notification.id)}/read`, {
                        method: 'PATCH',
                        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                    });
                    window.location.assign(notification.url);
                }
            });
            items.append(link);
        });
    };
    const refreshNotifications = () => loadNotifications().catch(() => {
        count.hidden = false;
        count.textContent = '!';
        count.setAttribute('aria-label', 'Notifications could not be refreshed');
        if (!panel.hidden) items.textContent = 'Notifications could not be loaded. Please refresh and try again.';
    });

    toggle.addEventListener('click', async () => {
        panel.hidden = !panel.hidden;
        toggle.setAttribute('aria-expanded', String(!panel.hidden));
        if (!panel.hidden) {
            await refreshNotifications();
        }
    });

    center.querySelector('[data-notification-read-all]').addEventListener('click', async () => {
        const response = await fetch('/notifications/read-all', {
            method: 'PATCH',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
        });
        if (!response.ok) {
            window.alert('Notifications could not be marked as read. Please try again.');
            return;
        }
        await refreshNotifications();
    });

    refreshNotifications();
    window.setInterval(refreshNotifications, 45000);
});

const chatbot = document.querySelector('[data-chatbot]');

if (chatbot) {
    const form = chatbot.querySelector('[data-chat-form]');
    const input = form.querySelector('textarea');
    const messages = chatbot.querySelector('[data-chat-messages]');
    const suggestions = chatbot.querySelector('[data-chat-suggestions]');
    const sendButton = form.querySelector('button[type="submit"]');
    const clearButton = chatbot.querySelector('[data-chat-clear]');

    const appendMessage = (text, type) => {
        const message = document.createElement('p');
        message.className = `chat-bubble ${type}-message`;
        message.textContent = text;
        messages.append(message);
        messages.scrollTop = messages.scrollHeight;
    };

    const renderSuggestions = (items) => {
        if (!Array.isArray(items) || items.length === 0) return;
        suggestions.replaceChildren();
        items.forEach((text) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'suggestion-button';
            button.textContent = text;
            suggestions.append(button);
        });
    };

    const sendMessage = async (message) => {
        if (!message.trim() || sendButton.disabled) return;

        appendMessage(message, 'student');
        input.value = '';
        sendButton.disabled = true;
        sendButton.textContent = 'Sending...';

        try {
            const response = await fetch(chatbot.dataset.endpoint, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ message }),
            });
            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.error || 'The assistant is temporarily unavailable. Please try again later.');
            }

            appendMessage(data.answer, 'assistant');
            renderSuggestions(data.suggestions);
        } catch (error) {
            appendMessage(error.message || 'The assistant is temporarily unavailable. Please try again later.', 'assistant');
        } finally {
            sendButton.disabled = false;
            sendButton.textContent = 'Send';
            input.focus();
        }
    };

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        sendMessage(input.value);
    });

    suggestions.addEventListener('click', (event) => {
        const button = event.target.closest('button.suggestion-button');
        if (button) sendMessage(button.textContent);
    });

    clearButton.addEventListener('click', async () => {
        if (!window.confirm('Clear all saved chat history for your account?')) return;

        clearButton.disabled = true;
        try {
            const response = await fetch(chatbot.dataset.clearEndpoint, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
            });
            if (!response.ok) {
                throw new Error('Chat history could not be cleared. Please try again.');
            }

            messages.replaceChildren();
            appendMessage('Hello! I can help with SmartDoc document requests, requirements, fees, and procedures. What would you like to know?', 'assistant');
        } catch (error) {
            window.alert(error.message || 'Chat history could not be cleared. Please try again.');
        } finally {
            clearButton.disabled = false;
        }
    });

    input.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            form.requestSubmit();
        }
    });
}
