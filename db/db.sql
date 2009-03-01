CREATE TABLE opts (opt,val);
CREATE UNIQUE INDEX idx_opt ON opts(opt);
INSERT INTO opts (opt,val) VALUES('dbversion',1);

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
    email
);
CREATE UNIQUE INDEX idx_entries_pid ON entries(pid);
CREATE INDEX idx_entries_title ON entries(title);
CREATE INDEX idx_entries_created ON entries(created);
CREATE INDEX idx_entries_blog ON entries(blog);

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
    status
);
CREATE INDEX idx_comments_created ON comments(created);
CREATE INDEX idx_comments_pid ON comments(pid);
CREATE INDEX idx_comments_status ON comments(status);

CREATE TABLE tags (
    pid,
    tag,
    PRIMARY KEY (pid, tag)
);
CREATE INDEX idx_tags_pid ON tags(pid);
CREATE INDEX idx_tags_tag ON tags(tag);
