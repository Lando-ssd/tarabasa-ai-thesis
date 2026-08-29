-- PLACEHOLDER curriculum_guides seed data.
-- Per explicit agreement: real DepEd MATATAG English + Reading & Literacy CG
-- content (Grade 1-3 sections only) will be extracted and swapped in later as
-- its own focused task. curriculum_guides is pure seed data — nothing that
-- reads this table needs to change when the real content replaces this.

INSERT INTO curriculum_guides (topic, grade_level, skill_focus, sample_vocabulary) VALUES
('Phonics — Letter Blending', 'Grade 1', 'Phonemic Awareness', '["cat","dog","sun","mat","hat","bed","pig","cup"]'),
('Sight Words — High Frequency', 'Grade 1', 'Word Recognition', NULL),
('Phonics — Consonant Sounds', 'Grade 1', 'Sound-Symbol Correspondence', '["ball","fan","map","net","top","bag"]'),
('Vocabulary — Family and School', 'Grade 1', 'Oral Language', '["mother","father","teacher","school","book","chair"]'),
('Reading Comprehension — Short Stories', 'Grade 2', 'Main Idea', NULL),
('Vocabulary — Community Words', 'Grade 2', 'Word Recognition', '["market","doctor","store","street","house","farmer"]'),
('Sentence Building — Simple Sentences', 'Grade 2', 'Grammar Awareness', '["run","jump","play","read","write","sing"]'),
('Phonics — Blends and Digraphs', 'Grade 2', 'Phonics and Word Study', '["ship","chair","thin","shop","chin","that"]'),
('Vocabulary in Context', 'Grade 3', 'Context Clues', '["enormous","curious","gentle","brave","ancient","clever"]'),
('Reading Comprehension — Informational Texts', 'Grade 3', 'Identifying Main Idea and Details', NULL),
('Sentence Building — Compound Sentences', 'Grade 3', 'Grammar Awareness and Grammatical Structures', '["because","and","but","so","however","although"]'),
('Vocabulary — Science Topics', 'Grade 3', 'Content-Specific Words', '["plant","animal","water","energy","weather","forest"]');
