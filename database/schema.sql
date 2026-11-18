-- Datový model: Course, Participant, Enrollment
-- SQLite schema

CREATE TABLE IF NOT EXISTS courses (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    name       TEXT    NOT NULL,
    code       TEXT    NOT NULL,
    capacity   INTEGER NOT NULL CHECK (capacity > 0),
    starts_at  TEXT    NOT NULL
);

-- Kód kurzu musí být jedinečný
CREATE UNIQUE INDEX IF NOT EXISTS idx_courses_code_unique ON courses(code);

CREATE TABLE IF NOT EXISTS participants (
    id    INTEGER PRIMARY KEY AUTOINCREMENT,
    name  TEXT NOT NULL,
    email TEXT NOT NULL
);

-- E-mail účastníka musí být jedinečný
CREATE UNIQUE INDEX IF NOT EXISTS idx_participants_email_unique ON participants(email);

CREATE TABLE IF NOT EXISTS enrollments (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    course_id      INTEGER NOT NULL REFERENCES courses(id) ON DELETE CASCADE,
    participant_id INTEGER NOT NULL REFERENCES participants(id) ON DELETE CASCADE,
    enrolled_at    TEXT    NOT NULL,
    status         TEXT    NOT NULL DEFAULT 'enrolled'
                   CHECK (status IN ('enrolled', 'cancelled', 'completed'))
);

CREATE INDEX IF NOT EXISTS idx_enrollments_course ON enrollments(course_id);
CREATE INDEX IF NOT EXISTS idx_enrollments_participant ON enrollments(participant_id);

-- Účastník se nesmí přihlásit dvakrát: jedna AKTIVNÍ (status = 'enrolled')
-- přihláška na kurz a účastníka (částečný unikátní index). Po zrušení
-- přihlášky (status = 'cancelled') se může přihlásit znovu.
CREATE UNIQUE INDEX IF NOT EXISTS idx_enrollments_unique_active
    ON enrollments(course_id, participant_id)
    WHERE status = 'enrolled';
