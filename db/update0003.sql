CREATE TABLE subscriptions (
    pid,
    mail,
    PRIMARY KEY (pid, mail)
);
CREATE INDEX idx_subscriptions_pid ON subscriptions(pid);
CREATE INDEX idx_subscriptions_mail ON subscriptions(mail);

CREATE TABLE optin (
    mail PRIMARY KEY,
    optin DEFAULT 0,
    key
);

