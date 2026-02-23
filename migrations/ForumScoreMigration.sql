-- Migration to add forum_post_score table
CREATE TABLE forum_post_score (
    id INT AUTO_INCREMENT PRIMARY KEY,
    forum_id INT NOT NULL,
    calculated_score DECIMAL(10, 4) NOT NULL DEFAULT 0.0000,
    likes_count INT NOT NULL DEFAULT 0,
    dislikes_count INT NOT NULL DEFAULT 0,
    comments_count INT NOT NULL DEFAULT 0,
    views_count INT NOT NULL DEFAULT 0,
    base_score DECIMAL(10, 4) NOT NULL DEFAULT 1.0000,
    last_calculated_at DATETIME NOT NULL,
    last_activity_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_forum (forum_id),
    INDEX idx_calculated_score (calculated_score),
    INDEX idx_last_activity (last_activity_at),
    INDEX idx_last_calculated (last_calculated_at),
    
    FOREIGN KEY (forum_id) REFERENCES forum (id) ON DELETE CASCADE
);

-- Migration to add forum_dislike table (if not exists)
CREATE TABLE IF NOT EXISTS forum_dislike (
    id INT AUTO_INCREMENT PRIMARY KEY,
    forum_id INT NOT NULL,
    user_id INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_forum_user (forum_id, user_id),
    INDEX idx_forum (forum_id),
    INDEX idx_user (user_id),
    
    FOREIGN KEY (forum_id) REFERENCES forum (id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE
);

-- Trigger to automatically create score record when forum post is created
DELIMITER //
CREATE TRIGGER after_forum_insert
AFTER INSERT ON forum
FOR EACH ROW
BEGIN
    INSERT INTO forum_post_score (
        forum_id, 
        calculated_score, 
        likes_count, 
        dislikes_count, 
        comments_count, 
        views_count, 
        base_score, 
        last_calculated_at, 
        last_activity_at
    ) VALUES (
        NEW.id,
        1.0000,
        0,
        0,
        0,
        0,
        1.0000,
        NOW(),
        NOW()
    );
END//
DELIMITER ;

-- View for optimized score queries
CREATE VIEW forum_score_view AS
SELECT 
    f.*,
    fps.calculated_score,
    fps.likes_count,
    fps.dislikes_count,
    fps.comments_count,
    fps.views_count,
    fps.base_score,
    fps.last_calculated_at,
    fps.last_activity_at,
    TIMESTAMPDIFF(HOUR, f.date_creation, NOW()) as age_in_hours,
    TIMESTAMPDIFF(HOUR, fps.last_activity_at, NOW()) as hours_since_activity,
    (fps.likes_count + fps.dislikes_count + fps.comments_count) as total_interactions
FROM forum f
JOIN forum_post_score fps ON f.id = fps.forum_id;

-- Stored procedure for efficient score calculation
DELIMITER //
CREATE PROCEDURE CalculateForumScore(IN forum_id_param INT)
BEGIN
    UPDATE forum_post_score fps
    JOIN forum f ON fps.forum_id = f.id
    LEFT JOIN (
        SELECT forum_id, COUNT(*) as likes_count
        FROM forum_like fl
        WHERE fl.forum_id = forum_id_param
        GROUP BY forum_id
    ) likes ON f.id = likes.forum_id
    LEFT JOIN (
        SELECT forum_id, COUNT(*) as dislikes_count
        FROM forum_dislike fd
        WHERE fd.forum_id = forum_id_param
        GROUP BY forum_id
    ) dislikes ON f.id = dislikes.forum_id
    LEFT JOIN (
        SELECT forum_id, COUNT(*) as comments_count
        FROM forum_reponse fr
        WHERE fr.forum_id = forum_id_param
        GROUP BY forum_id
    ) comments ON f.id = comments.forum_id
    WHERE fps.forum_id = forum_id_param
    SET 
        fps.likes_count = COALESCE(likes.likes_count, 0),
        fps.dislikes_count = COALESCE(dislikes.dislikes_count, 0),
        fps.comments_count = COALESCE(comments.comments_count, 0),
        fps.calculated_score = (
            (COALESCE(likes.likes_count, 0) * 2.0) + 
            (COALESCE(dislikes.dislikes_count, 0) * -1.0) + 
            (COALESCE(comments.comments_count, 0) * 3.0) + 
            (fps.views_count * 0.1)
        ) * EXP(-TIMESTAMPDIFF(HOUR, f.date_creation, NOW()) * 0.01) + fps.base_score,
        fps.last_calculated_at = NOW();
END//
DELIMITER ;
