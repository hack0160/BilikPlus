-- BilikGo database install script (sqlite)
-- Creates all tables (incl. images for photo storage) and seeds demo data (password: Demo@123)
-- Run with: sqlite3 data/bilikgo.sqlite < database/install_sqlite.sql
-- (Normally unnecessary: the app creates this database automatically.)

CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT NOT NULL UNIQUE,
            phone TEXT DEFAULT '',
            password_hash TEXT NOT NULL,
            role VARCHAR(10) NOT NULL,
            status VARCHAR(10) NOT NULL DEFAULT 'active',
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );

CREATE TABLE IF NOT EXISTS listings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            owner_id INTEGER NOT NULL,
            title TEXT NOT NULL,
            area TEXT NOT NULL,
            city TEXT NOT NULL,
            address TEXT DEFAULT '',
            price INTEGER NOT NULL,
            room_type TEXT NOT NULL,
            property_type TEXT NOT NULL,
            furnishing TEXT NOT NULL,
            gender_pref VARCHAR(10) NOT NULL DEFAULT 'Any',
            amenities TEXT,
            description TEXT,
            image TEXT DEFAULT '',
            status VARCHAR(10) NOT NULL DEFAULT 'active',
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_listing_owner FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
        );

CREATE TABLE IF NOT EXISTS swipes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tenant_id INTEGER NOT NULL,
            listing_id INTEGER NOT NULL,
            direction VARCHAR(5) NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT uq_swipe UNIQUE (tenant_id, listing_id),
            CONSTRAINT fk_swipe_tenant  FOREIGN KEY (tenant_id)  REFERENCES users(id)    ON DELETE CASCADE,
            CONSTRAINT fk_swipe_listing FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE
        );

CREATE TABLE IF NOT EXISTS password_resets (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            token VARCHAR(64) NOT NULL UNIQUE,
            expires_at TEXT NOT NULL,
            used INTEGER NOT NULL DEFAULT 0,
            CONSTRAINT fk_reset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );

CREATE TABLE IF NOT EXISTS images (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            mime VARCHAR(30) NOT NULL,
            data BLOB NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );

-- ---- seed: users ----
INSERT INTO users (name,email,phone,password_hash,role) VALUES ('Site Admin','admin@bilikgo.test','012-0000001','$2y$10$M5or7wbzLcaz6y6JEcS3QOntUN5HhPgkwoYnAi1.gShxtgN3lb4te','admin');
INSERT INTO users (name,email,phone,password_hash,role) VALUES ('Aiman Properties','owner@bilikgo.test','012-0000002','$2y$10$M5or7wbzLcaz6y6JEcS3QOntUN5HhPgkwoYnAi1.gShxtgN3lb4te','owner');
INSERT INTO users (name,email,phone,password_hash,role) VALUES ('Mei Ling Homes','owner2@bilikgo.test','012-0000003','$2y$10$M5or7wbzLcaz6y6JEcS3QOntUN5HhPgkwoYnAi1.gShxtgN3lb4te','owner');
INSERT INTO users (name,email,phone,password_hash,role) VALUES ('Demo Tenant','tenant@bilikgo.test','012-0000004','$2y$10$M5or7wbzLcaz6y6JEcS3QOntUN5HhPgkwoYnAi1.gShxtgN3lb4te','tenant');

-- ---- seed: sample listings ----
INSERT INTO listings (owner_id,title,area,city,price,room_type,property_type,furnishing,gender_pref,amenities,description,image) VALUES (2,'Cozy medium room near MRT Cheras','Cheras','Kuala Lumpur',650,'Medium Room','Condominium','Fully furnished','Any','Wi-Fi,Aircond,Washing machine,Near MRT,Pool,Gym','5 min walk to MRT Taman Mutiara. Utilities included, friendly housemates, move-in ready.','assets/img/seed1.svg');
INSERT INTO listings (owner_id,title,area,city,price,room_type,property_type,furnishing,gender_pref,amenities,description,image) VALUES (2,'Master room with bathroom, Bangsar South','Bangsar South','Kuala Lumpur',1200,'Master Room','Serviced Residence','Fully furnished','Any','Wi-Fi,Aircond,Private bathroom,Pool,Gym,Parking','Attached bathroom, KL skyline view, walking distance to LRT Kerinchi and offices.','assets/img/seed2.svg');
INSERT INTO listings (owner_id,title,area,city,price,room_type,property_type,furnishing,gender_pref,amenities,description,image) VALUES (2,'Budget single room, SS15 Subang Jaya','SS15, Subang Jaya','Selangor',480,'Single Room','Apartment','Partially furnished','Female','Wi-Fi,Washing machine,Near LRT,Near college','Perfect for students — Taylor''s and INTI nearby. Quiet female-only unit.','assets/img/seed3.svg');
INSERT INTO listings (owner_id,title,area,city,price,room_type,property_type,furnishing,gender_pref,amenities,description,image) VALUES (2,'Big master room, Mont Kiara expat area','Mont Kiara','Kuala Lumpur',1500,'Master Room','Condominium','Fully furnished','Any','Wi-Fi,Aircond,Private bathroom,Pool,Gym,Parking,Security','Premium condo with full facilities, balcony, covered parking included.','assets/img/seed4.svg');
INSERT INTO listings (owner_id,title,area,city,price,room_type,property_type,furnishing,gender_pref,amenities,description,image) VALUES (3,'Medium room in landed house, Petaling Jaya','SS2, Petaling Jaya','Selangor',600,'Medium Room','Terrace House','Partially furnished','Male','Wi-Fi,Washing machine,Parking,Near food court','Quiet neighbourhood, famous SS2 food nearby, car porch parking available.','assets/img/seed5.svg');
INSERT INTO listings (owner_id,title,area,city,price,room_type,property_type,furnishing,gender_pref,amenities,description,image) VALUES (3,'Single room walk to Cyberjaya offices','Cyberjaya','Selangor',520,'Single Room','Serviced Residence','Fully furnished','Any','Wi-Fi,Aircond,Pool,Gym,Shuttle','Ideal for tech workers. Shuttle to major offices, utilities capped at RM50.','assets/img/seed6.svg');
INSERT INTO listings (owner_id,title,area,city,price,room_type,property_type,furnishing,gender_pref,amenities,description,image) VALUES (3,'Master room, Setia Alam with parking','Setia Alam','Selangor',850,'Master Room','Double Storey House','Fully furnished','Any','Wi-Fi,Aircond,Private bathroom,Parking,Garden','Spacious landed home near Setia City Mall. Includes one car park bay.','assets/img/seed7.svg');
INSERT INTO listings (owner_id,title,area,city,price,room_type,property_type,furnishing,gender_pref,amenities,description,image) VALUES (2,'Studio-style room, KLCC fringe','Kampung Baru','Kuala Lumpur',980,'Studio','Apartment','Fully furnished','Any','Wi-Fi,Aircond,Kitchenette,Near LRT','Own kitchenette and entrance. 10 min to KLCC, halal eateries downstairs.','assets/img/seed8.svg');
INSERT INTO listings (owner_id,title,area,city,price,room_type,property_type,furnishing,gender_pref,amenities,description,image) VALUES (3,'Female unit medium room, Wangsa Maju','Wangsa Maju','Kuala Lumpur',580,'Medium Room','Condominium','Fully furnished','Female','Wi-Fi,Aircond,Pool,Near LRT,Security','Female-only unit, 7 min walk to LRT Wangsa Maju, near AEON Big.','assets/img/seed9.svg');
INSERT INTO listings (owner_id,title,area,city,price,room_type,property_type,furnishing,gender_pref,amenities,description,image) VALUES (2,'Low-deposit single room, Kepong','Kepong','Kuala Lumpur',450,'Single Room','Flat','Partially furnished','Male','Wi-Fi,Washing machine,Near market','One month deposit only. Near Kepong market and bus routes.','assets/img/seed10.svg');
