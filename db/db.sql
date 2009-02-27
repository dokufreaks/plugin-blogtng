CREATE TABLE articles (
    pid PRIMARY KEY,
    page,
    title,
    intro,
    image,
    created INTEGER,
    lastmod INTEGER,
    author,
    login
);
CREATE UNIQUE INDEX idx_pid ON articles(pid);
CREATE INDEX idx_title ON articles(title);
CREATE INDEX idx_created ON articles(created);


