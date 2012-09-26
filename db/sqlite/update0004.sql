-- add ip column to comments table
CREATE TEMPORARY TABLE temp_comments (
    cid INTEGER PRIMARY KEY,
    pid,
    source,
    name,
    mail,
    web,
    avatar,
    created INTEGER,
    text,
    status
);
INSERT INTO temp_comments 
    SELECT cid, pid, source, name, mail, web, avatar, created, text, status
    FROM comments
;

DROP TABLE comments;
CREATE TABLE comments (
    cid INTEGER PRIMARY KEY,
    pid,
    source,
    name,
    mail,
    web,
    avatar,
    created INTEGER,
    text,
    status,
    ip
);
CREATE INDEX idx_comments_created ON comments(created);
CREATE INDEX idx_comments_pid ON comments(pid);
CREATE INDEX idx_comments_status ON comments(status);

INSERT INTO comments 
    SELECT cid, pid, source, name, mail, web, avatar, created, text, status, null
    FROM temp_comments
;
DROP TABLE temp_comments;

-- add comments_enabled column to entries table
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
    email
);
INSERT INTO temp_entries 
    SELECT pid, page, title, blog, image, created, lastmod, author, login, email
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
    comments_enabled
);
CREATE UNIQUE INDEX idx_entries_pid ON entries(pid);
CREATE INDEX idx_entries_title ON entries(title);
CREATE INDEX idx_entries_created ON entries(created);
CREATE INDEX idx_entries_blog ON entries(blog);

INSERT INTO entries
    SELECT pid, page, title, blog, image, created, lastmod, author, login, email, 1
    FROM temp_entries
;
DROP TABLE temp_entries;
