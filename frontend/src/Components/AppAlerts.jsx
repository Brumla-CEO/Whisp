import React, { useEffect, useState } from 'react';

let alertIdCounter = 0;

const normalizeDetail = (detail) => {
    if (!detail) {
        return { message: 'Neznámá událost.', type: 'info', timeout: 4200 };
    }

    if (typeof detail === 'string') {
        return { message: detail, type: 'info', timeout: 4200 };
    }

    return {
        message: detail.message || 'Neznámá událost.',
        type: detail.type || 'info',
        timeout: typeof detail.timeout === 'number' ? detail.timeout : 4200,
    };
};

const AppAlerts = () => {
    const [alerts, setAlerts] = useState([]);

    useEffect(() => {
        const handleNotify = (event) => {
            const detail = normalizeDetail(event.detail);
            const id = ++alertIdCounter;

            setAlerts((prev) => [...prev, { ...detail, id }]);

            window.setTimeout(() => {
                setAlerts((prev) => prev.filter((alert) => alert.id !== id));
            }, detail.timeout);
        };

        window.addEventListener('app-notify', handleNotify);
        return () => window.removeEventListener('app-notify', handleNotify);
    }, []);

    const dismissAlert = (id) => {
        setAlerts((prev) => prev.filter((alert) => alert.id !== id));
    };

    return (
        <div className="app-alerts-stack" aria-live="polite" aria-atomic="true">
            {alerts.map((alert) => (
                <div key={alert.id} className={`app-alert app-alert--${alert.type}`}>
                    <div className="app-alert__content">{alert.message}</div>
                    <button className="app-alert__close" onClick={() => dismissAlert(alert.id)} aria-label="Zavřít notifikaci">
                        ✕
                    </button>
                </div>
            ))}
        </div>
    );
};

export default AppAlerts;
