CREATE TABLE sendcounts (
  line_id varchar(255) NOT NULL,
  target_month char(7) NOT NULL,
  send_count int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (line_id, target_month),
  CONSTRAINT sendcounts_line_id_foreign FOREIGN KEY (line_id) REFERENCES users (line_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
