-- ============================================================
--  Nexus Social Media — Complete Setup SQL
--  Run this in phpMyAdmin to set up the full database
-- ============================================================

CREATE DATABASE IF NOT EXISTS social_media CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE social_media;

-- Users
CREATE TABLE user (
    user_id      INT           NOT NULL AUTO_INCREMENT,
    username     VARCHAR(50)   NOT NULL UNIQUE,
    status       VARCHAR(20)   NOT NULL DEFAULT 'active',
    email        VARCHAR(255)  NOT NULL UNIQUE,
    password     VARCHAR(255)  NOT NULL,
    bio          VARCHAR(500),
    is_deleted   BOOLEAN       NOT NULL DEFAULT FALSE,
    created_at   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT pk_user PRIMARY KEY (user_id)
);

-- Follows (fixed composite primary key)
CREATE TABLE follows (
    follower_id   INT       NOT NULL,
    following_id  INT       NOT NULL,
    followed_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_follows           PRIMARY KEY (follower_id, following_id),
    CONSTRAINT fk_follows_follower  FOREIGN KEY (follower_id)  REFERENCES user (user_id) ON DELETE CASCADE,
    CONSTRAINT fk_follows_following FOREIGN KEY (following_id) REFERENCES user (user_id) ON DELETE CASCADE
);

-- Posts
CREATE TABLE posts (
    post_id     INT          NOT NULL AUTO_INCREMENT,
    user_id     INT          NOT NULL,
    content     TEXT,
    visibility  VARCHAR(20)  NOT NULL DEFAULT 'public',
    media_url   VARCHAR(500),
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_posts      PRIMARY KEY (post_id),
    CONSTRAINT fk_posts_user FOREIGN KEY (user_id) REFERENCES user (user_id) ON DELETE CASCADE
);

-- Hashtags
CREATE TABLE hashtags (
    hashtag_id  INT          NOT NULL AUTO_INCREMENT,
    tag_name    VARCHAR(100) NOT NULL UNIQUE,
    usage_count INT          NOT NULL DEFAULT 0,
    CONSTRAINT pk_hashtags PRIMARY KEY (hashtag_id)
);

-- Comments
CREATE TABLE comments (
    comment_id  INT       NOT NULL AUTO_INCREMENT,
    post_id     INT       NOT NULL,
    user_id     INT       NOT NULL,
    content     TEXT      NOT NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_comments      PRIMARY KEY (comment_id),
    CONSTRAINT fk_comments_post FOREIGN KEY (post_id) REFERENCES posts  (post_id) ON DELETE CASCADE,
    CONSTRAINT fk_comments_user FOREIGN KEY (user_id) REFERENCES user   (user_id) ON DELETE CASCADE
);

-- Likes
CREATE TABLE likes (
    like_id   INT       NOT NULL AUTO_INCREMENT,
    user_id   INT       NOT NULL,
    post_id   INT       NOT NULL,
    liked_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_likes      PRIMARY KEY (like_id),
    CONSTRAINT uq_likes      UNIQUE (user_id, post_id),
    CONSTRAINT fk_likes_user FOREIGN KEY (user_id) REFERENCES user  (user_id) ON DELETE CASCADE,
    CONSTRAINT fk_likes_post FOREIGN KEY (post_id) REFERENCES posts (post_id) ON DELETE CASCADE
);

-- Messages
CREATE TABLE messages (
    message_id  INT       NOT NULL AUTO_INCREMENT,
    reciever_id INT       NOT NULL,
    sender_id   INT       NOT NULL,
    content     TEXT      NOT NULL,
    is_read     BOOLEAN   NOT NULL DEFAULT FALSE,
    sent_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_messages        PRIMARY KEY (message_id),
    CONSTRAINT fk_messages_sender FOREIGN KEY (sender_id)   REFERENCES user (user_id) ON DELETE CASCADE,
    CONSTRAINT fk_messages_recv   FOREIGN KEY (reciever_id) REFERENCES user (user_id) ON DELETE CASCADE
);

-- Notifications
CREATE TABLE notifications (
    notif_id    INT          NOT NULL AUTO_INCREMENT,
    user_id     INT          NOT NULL,
    actor_id    INT          NOT NULL,
    type        VARCHAR(50)  NOT NULL,
    is_read     BOOLEAN      NOT NULL DEFAULT FALSE,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_notifications  PRIMARY KEY (notif_id),
    CONSTRAINT fk_notif_user     FOREIGN KEY (user_id)  REFERENCES user (user_id) ON DELETE CASCADE,
    CONSTRAINT fk_notif_actor    FOREIGN KEY (actor_id) REFERENCES user (user_id) ON DELETE CASCADE
);
