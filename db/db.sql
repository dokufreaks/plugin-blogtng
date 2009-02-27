CREATE TABLE opts (opt,val);
CREATE UNIQUE INDEX idx_opt ON opts(opt);
INSERT INTO opts (opt,val) VALUES('dbversion',1);

CREATE TABLE articles (
    pid PRIMARY KEY,
    page,
    title,
    image,
    created INTEGER,
    lastmod INTEGER,
    author,
    login,
    email
);
CREATE UNIQUE INDEX idx_pid ON articles(pid);
CREATE INDEX idx_title ON articles(title);
CREATE INDEX idx_created ON articles(created);


