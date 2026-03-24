USE lms_db;

UPDATE student s
JOIN (
  SELECT co.stu_id, MIN(c.track_id) AS inferred_track_id
  FROM courseorder co
  JOIN course c ON c.course_id = co.course_id
  WHERE c.track_id IS NOT NULL
  GROUP BY co.stu_id
) x ON x.stu_id = s.stu_id
SET s.preferred_track_id = x.inferred_track_id
WHERE s.preferred_track_id IS NULL;

UPDATE student
SET experience_level = 'beginner'
WHERE experience_level IS NULL OR TRIM(experience_level) = '';

SELECT stu_id, stu_email, preferred_track_id, experience_level
FROM student
ORDER BY stu_id;
