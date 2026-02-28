-- backend/init.sql

-- 1. Roles (RBAC)
CREATE TABLE IF NOT EXISTS roles (
                                     id SERIAL PRIMARY KEY,
                                     name VARCHAR(50) NOT NULL UNIQUE,
                                     description TEXT
);

-- Vložíme defaultní role, pokud neexistují
INSERT INTO roles (name, description) VALUES
                                          ('admin', 'Administrator with full access'),
                                          ('user', 'Standard user')
ON CONFLICT (name) DO NOTHING;

-- 2. Users
CREATE TABLE IF NOT EXISTS users (
                                     id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                                     username VARCHAR(50) NOT NULL UNIQUE,
                                     email VARCHAR(100) NOT NULL UNIQUE,
                                     password_hash VARCHAR(255) NOT NULL,
                                     role_id INTEGER REFERENCES roles(id),
                                     avatar_url TEXT,
                                     bio VARCHAR(200),
                                     status VARCHAR(20) DEFAULT 'offline', -- online, offline
                                     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 3. Sessions (JWT Invalidace)
CREATE TABLE IF NOT EXISTS sessions (
                                        id SERIAL PRIMARY KEY,
                                        user_id UUID REFERENCES users(id) ON DELETE CASCADE,
                                        token TEXT NOT NULL,
                                        expires_at TIMESTAMP NOT NULL,
                                        is_active BOOLEAN DEFAULT TRUE,
                                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 4. Rooms (Chat groups & DMs)
CREATE TABLE IF NOT EXISTS rooms (
                                     id SERIAL PRIMARY KEY,
                                     name VARCHAR(100), -- Pro skupiny
                                     type VARCHAR(20) NOT NULL, -- 'dm' nebo 'group'
                                     owner_id UUID REFERENCES users(id) ON DELETE SET NULL, -- Kdo skupinu založil
                                     avatar_url TEXT,
                                     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 5. Room Memberships
CREATE TABLE IF NOT EXISTS room_memberships (
                                                room_id INTEGER REFERENCES rooms(id) ON DELETE CASCADE,
                                                user_id UUID REFERENCES users(id) ON DELETE CASCADE,
                                                role VARCHAR(20) DEFAULT 'member', -- 'admin', 'member'
                                                joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                                PRIMARY KEY (room_id, user_id)
);

-- 6. Messages
CREATE TABLE IF NOT EXISTS messages (
                                        id SERIAL PRIMARY KEY,
                                        room_id INTEGER REFERENCES rooms(id) ON DELETE CASCADE,
                                        sender_id UUID REFERENCES users(id) ON DELETE SET NULL, -- Pokud se uživatel smaže, zpráva zůstane (autor NULL)
                                        content TEXT NOT NULL,
                                        reply_to_id INTEGER REFERENCES messages(id) ON DELETE SET NULL,
                                        is_edited BOOLEAN DEFAULT FALSE,
                                        is_deleted BOOLEAN DEFAULT FALSE,
                                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 7. Friendships
CREATE TABLE IF NOT EXISTS friendships (
                                           id SERIAL PRIMARY KEY,
                                           requester_id UUID REFERENCES users(id) ON DELETE CASCADE,
                                           addressee_id UUID REFERENCES users(id) ON DELETE CASCADE,
                                           status VARCHAR(20) NOT NULL, -- 'pending', 'accepted', 'rejected'
                                           created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                           UNIQUE(requester_id, addressee_id)
);

-- 8. Activity Logs (Admin)
CREATE TABLE IF NOT EXISTS activity_logs (
                                             id SERIAL PRIMARY KEY,
                                             user_id UUID REFERENCES users(id) ON DELETE SET NULL,
                                             action VARCHAR(50) NOT NULL,
                                             details TEXT,
                                             ip_address VARCHAR(45),
                                             timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 9. Notifications (Offline messages)
CREATE TABLE IF NOT EXISTS notifications (
                                             id SERIAL PRIMARY KEY,
                                             user_id UUID REFERENCES users(id) ON DELETE CASCADE,
                                             room_id INTEGER REFERENCES rooms(id) ON DELETE CASCADE,
                                             type VARCHAR(50) NOT NULL, -- 'message', 'friend_req'
                                             content TEXT,
                                             is_read BOOLEAN DEFAULT FALSE,
                                             created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);