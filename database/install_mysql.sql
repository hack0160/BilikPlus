-- BilikGo database install script (mysql)
-- All tables (rent/sale + multi-photo) + demo seed (password: Demo@123)

CREATE TABLE IF NOT EXISTS users (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL UNIQUE,
            phone VARCHAR(255) DEFAULT '',
            password_hash VARCHAR(255) NOT NULL,
            role VARCHAR(10) NOT NULL,
            status VARCHAR(10) NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS listings (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            owner_id INT UNSIGNED NOT NULL,
            title VARCHAR(255) NOT NULL,
            area VARCHAR(255) NOT NULL,
            city VARCHAR(255) NOT NULL,
            address VARCHAR(255) DEFAULT '',
            price INTEGER NOT NULL,
            listing_type VARCHAR(6) NOT NULL DEFAULT 'rent',
            room_type VARCHAR(255) NOT NULL,
            property_type VARCHAR(255) NOT NULL,
            furnishing VARCHAR(255) NOT NULL,
            gender_pref VARCHAR(10) NOT NULL DEFAULT 'Any',
            amenities TEXT,
            description TEXT,
            image VARCHAR(255) DEFAULT '',
            status VARCHAR(10) NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_listing_owner FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS swipes (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT UNSIGNED NOT NULL,
            listing_id INT UNSIGNED NOT NULL,
            direction VARCHAR(5) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT uq_swipe UNIQUE (tenant_id, listing_id),
            CONSTRAINT fk_swipe_tenant  FOREIGN KEY (tenant_id)  REFERENCES users(id)    ON DELETE CASCADE,
            CONSTRAINT fk_swipe_listing FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_resets (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            token VARCHAR(64) NOT NULL UNIQUE,
            expires_at DATETIME NOT NULL,
            used INTEGER NOT NULL DEFAULT 0,
            CONSTRAINT fk_reset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS images (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            listing_id INTEGER,
            sort INTEGER NOT NULL DEFAULT 0,
            mime VARCHAR(30) NOT NULL,
            data MEDIUMBLOB NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- seed: users ----
INSERT INTO users (name,email,phone,password_hash,role) VALUES ('Site Admin','admin@bilikgo.test','012-0000001','$2y$10$xyukh6t0qgrT20mSdKc3vucUFUbexM4a0mUlE3HXa2nz3Aldvupvu','admin');
INSERT INTO users (name,email,phone,password_hash,role) VALUES ('Aiman Properties','owner@bilikgo.test','012-0000002','$2y$10$xyukh6t0qgrT20mSdKc3vucUFUbexM4a0mUlE3HXa2nz3Aldvupvu','owner');
INSERT INTO users (name,email,phone,password_hash,role) VALUES ('Mei Ling Homes','owner2@bilikgo.test','012-0000003','$2y$10$xyukh6t0qgrT20mSdKc3vucUFUbexM4a0mUlE3HXa2nz3Aldvupvu','owner');
INSERT INTO users (name,email,phone,password_hash,role) VALUES ('Demo Tenant','tenant@bilikgo.test','012-0000004','$2y$10$xyukh6t0qgrT20mSdKc3vucUFUbexM4a0mUlE3HXa2nz3Aldvupvu','tenant');

-- ---- seed: listings (rent + sale) ----
INSERT INTO listings (owner_id,title,area,city,price,listing_type,room_type,property_type,furnishing,gender_pref,amenities,description,image) VALUES (2,'Cozy medium room near MRT Cheras','Cheras','Kuala Lumpur',650,'rent','Medium Room','Condominium','Fully furnished','Any','Wi-Fi,Aircond,Washing machine,Near MRT,Pool,Gym','5 min walk to MRT Taman Mutiara. Utilities included, friendly housemates, move-in ready.','assets/img/seed1.svg');
INSERT INTO listings (owner_id,title,area,city,price,listing_type,room_type,property_type,furnishing,gender_pref,amenities,description,image) VALUES (2,'Master room with bathroom, Bangsar South','Bangsar South','Kuala Lumpur',1200,'rent','Master Room','Serviced Residence','Fully furnished','Any','Wi-Fi,Aircond,Private bathroom,Pool,Gym,Parking','Attached bathroom, KL skyline view, walking distance to LRT Kerinchi and offices.','assets/img/seed2.svg');
INSERT INTO listings (owner_id,title,area,city,price,listing_type,room_type,property_type,furnishing,gender_pref,amenities,description,image) VALUES (2,'Budget single room, SS15 Subang Jaya','SS15, Subang Jaya','Selangor',480,'rent','Single Room','Apartment','Partially furnished','Female','Wi-Fi,Washing machine,Near LRT,Near college','Perfect for students — Taylor''s and INTI nearby. Quiet female-only unit.','assets/img/seed3.svg');
INSERT INTO listings (owner_id,title,area,city,price,listing_type,room_type,property_type,furnishing,gender_pref,amenities,description,image) VALUES (2,'Big master room, Mont Kiara expat area','Mont Kiara','Kuala Lumpur',1500,'rent','Master Room','Condominium','Fully furnished','Any','Wi-Fi,Aircond,Private bathroom,Pool,Gym,Parking,Security','Premium condo with full facilities, balcony, covered parking included.','assets/img/seed4.svg');
INSERT INTO listings (owner_id,title,area,city,price,listing_type,room_type,property_type,furnishing,gender_pref,amenities,description,image) VALUES (3,'Medium room in landed house, Petaling Jaya','SS2, Petaling Jaya','Selangor',600,'rent','Medium Room','Terrace House','Partially furnished','Male','Wi-Fi,Washing machine,Parking,Near food court','Quiet neighbourhood, famous SS2 food nearby, car porch parking available.','assets/img/seed5.svg');
INSERT INTO listings (owner_id,title,area,city,price,listing_type,room_type,property_type,furnishing,gender_pref,amenities,description,image) VALUES (3,'Single room walk to Cyberjaya offices','Cyberjaya','Selangor',520,'rent','Single Room','Serviced Residence','Fully furnished','Any','Wi-Fi,Aircond,Pool,Gym,Shuttle','Ideal for tech workers. Shuttle to major offices, utilities capped at RM50.','assets/img/seed6.svg');
INSERT INTO listings (owner_id,title,area,city,price,listing_type,room_type,property_type,furnishing,gender_pref,amenities,description,image) VALUES (3,'Master room, Setia Alam with parking','Setia Alam','Selangor',850,'rent','Master Room','Double Storey House','Fully furnished','Any','Wi-Fi,Aircond,Private bathroom,Parking,Garden','Spacious landed home near Setia City Mall. Includes one car park bay.','assets/img/seed7.svg');
INSERT INTO listings (owner_id,title,area,city,price,listing_type,room_type,property_type,furnishing,gender_pref,amenities,description,image) VALUES (2,'Studio-style room, KLCC fringe','Kampung Baru','Kuala Lumpur',980,'rent','Studio','Apartment','Fully furnished','Any','Wi-Fi,Aircond,Kitchenette,Near LRT','Own kitchenette and entrance. 10 min to KLCC, halal eateries downstairs.','assets/img/seed8.svg');
INSERT INTO listings (owner_id,title,area,city,price,listing_type,room_type,property_type,furnishing,gender_pref,amenities,description,image) VALUES (3,'Female unit medium room, Wangsa Maju','Wangsa Maju','Kuala Lumpur',580,'rent','Medium Room','Condominium','Fully furnished','Female','Wi-Fi,Aircond,Pool,Near LRT,Security','Female-only unit, 7 min walk to LRT Wangsa Maju, near AEON Big.','assets/img/seed9.svg');
INSERT INTO listings (owner_id,title,area,city,price,listing_type,room_type,property_type,furnishing,gender_pref,amenities,description,image) VALUES (2,'Low-deposit single room, Kepong','Kepong','Kuala Lumpur',450,'rent','Single Room','Flat','Partially furnished','Male','Wi-Fi,Washing machine,Near market','One month deposit only. Near Kepong market and bus routes.','assets/img/seed10.svg');
INSERT INTO listings (owner_id,title,area,city,price,listing_type,room_type,property_type,furnishing,gender_pref,amenities,description,image) VALUES (2,'Renovated 3R2B condo, Cheras','Cheras','Kuala Lumpur',438000,'sale','Whole Unit','Condominium','Partially furnished','Any','Pool,Gym,Security,Parking,Near MRT','Freehold 1,012 sqft, new kitchen cabinets, 2 covered car parks.','assets/img/seed1.svg');
INSERT INTO listings (owner_id,title,area,city,price,listing_type,room_type,property_type,furnishing,gender_pref,amenities,description,image) VALUES (3,'Corner-lot double storey, Setia Alam','Setia Alam','Selangor',795000,'sale','Whole Unit','Double Storey House','Unfurnished','Any','Garden,Parking,Gated guarded','22x75 corner with extra land, move-in condition.','assets/img/seed7.svg');
INSERT INTO listings (owner_id,title,area,city,price,listing_type,room_type,property_type,furnishing,gender_pref,amenities,description,image) VALUES (2,'Compact studio, KLCC fringe (investor unit)','Kampung Baru','Kuala Lumpur',365000,'sale','Studio','Serviced Residence','Fully furnished','Any','Pool,Gym,Near LRT,Airbnb friendly','Tenanted at RM1.9k/mo, 5.9% gross yield, walk to LRT.','assets/img/seed8.svg');
INSERT INTO listings (owner_id,title,area,city,price,listing_type,room_type,property_type,furnishing,gender_pref,amenities,description,image) VALUES (3,'Family apartment, Wangsa Maju','Wangsa Maju','Kuala Lumpur',420000,'sale','Whole Unit','Apartment','Partially furnished','Any','Near LRT,Security,Playground','3 rooms 2 baths, 950 sqft, near AEON Big and LRT.','assets/img/seed9.svg');
