-- rename entries.comments_enabled column to entries.commentstatus
CREATE TEMPORARY TABLE temp_entries (
    pid PRIMARY KEY,
    page,
    title,
    blog,
    image,
    created INTEGER,
    lastmod INTEGER,
    author,
    login,
    mail
);
INSERT INTO temp_entries 
    SELECT pid, page, title, blog, image, created, lastmod, author, login, mail
    FROM entries
;

DROP TABLE entries;
CREATE TABLE entries (
    pid PRIMARY KEY,
    page,
    title,
    blog,
    image,
    created INTEGER,
    lastmod INTEGER,
    author,
    login,
    mail,
    commentstatus
);
CREATE UNIQUE INDEX idx_entries_pid ON entries(pid);
CREATE INDEX idx_entries_title ON entries(title);
CREATE INDEX idx_entries_created ON entries(created);
CREATE INDEX idx_entries_blog ON entries(blog);

INSERT INTO entries
    SELECT pid, page, title, blog, image, created, lastmod, author, login, mail, 'enabled'
    FROM temp_entries
;
DROP TABLE temp_entries;
