CREATE TABLE IF NOT EXISTS station (
    id integer PRIMARY KEY GENERATED BY DEFAULT AS IDENTITY,
    name text,
    place text
);

CREATE TABLE IF NOT EXISTS "user" (
    id integer PRIMARY KEY GENERATED BY DEFAULT AS IDENTITY,
    first_name text,
    last_name text,
    password text,
    email text UNIQUE,
    phone_number text,
    is_staff boolean DEFAULT false,
    at_station integer REFERENCES station(id) ON UPDATE CASCADE,
    CONSTRAINT con_work CHECK (NOT is_staff OR at_station IS NOT NULL)
);

CREATE TABLE IF NOT EXISTS connection (
    id integer PRIMARY KEY GENERATED BY DEFAULT AS IDENTITY,
    station1_id integer REFERENCES station(id) ON UPDATE CASCADE,
    station2_id integer REFERENCES station(id) ON UPDATE CASCADE,
    body text, -- connecting body of water
    distance integer, -- in meters
    kayaking_time integer, -- in minutes
    transport_time integer -- in minutes
);

CREATE TABLE IF NOT EXISTS trip (
    id integer PRIMARY KEY GENERATED BY DEFAULT AS IDENTITY,
    user_id integer REFERENCES "user"(id) ON UPDATE CASCADE,
    path integer[],
    distance integer,
    started_at timestamp DEFAULT CURRENT_TIMESTAMP,
    expected_at timestamp,
    finished_at timestamp,
    finished boolean DEFAULT FALSE
);

CREATE TABLE IF NOT EXISTS item (
    id integer PRIMARY KEY GENERATED BY DEFAULT AS IDENTITY,
    name text,
    cost_per_day money,
    mobile boolean DEFAULT false
);

CREATE TABLE IF NOT EXISTS equipment (
    station_id integer REFERENCES station(id) ON UPDATE CASCADE,
    item_id integer REFERENCES item(id) ON UPDATE CASCADE,
    available integer,
    maximum integer,
    CONSTRAINT con_avail CHECK (0 <= available)
);

CREATE TABLE IF NOT EXISTS reservation (
    id integer PRIMARY KEY GENERATED BY DEFAULT AS IDENTITY,
    user_id integer REFERENCES "user"(id) ON UPDATE CASCADE,
    status integer DEFAULT 1, -- 0 - confirmed, 1 - pending, 2 - rejected
    reserved_at timestamp DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS reserved_equipment (
    reservation_id integer REFERENCES reservation(id) ON UPDATE CASCADE,
    station_id integer REFERENCES station(id) ON UPDATE CASCADE,
    item_id integer REFERENCES item(id) ON UPDATE CASCADE,
    amount integer,
    reserved_from timestamp,
    reserved_to timestamp,
    received boolean DEFAULT false,
    received_at timestamp,
    returned boolean DEFAULT false,
    returned_at timestamp,
    price money,
    CONSTRAINT con_order CHECK (reserved_from <= reserved_to)
);

-- in the case of exceeding the capacity of a station,
-- a transport should be requested to stations with spare room
CREATE TABLE IF NOT EXISTS transport_request (
    id integer PRIMARY KEY GENERATED BY DEFAULT AS IDENTITY,
    from_station_id integer REFERENCES station(id) ON UPDATE CASCADE,
    item_id integer REFERENCES item(id) ON UPDATE CASCADE,
    amount integer, -- reaching 0 could indicate fulfilling the request
    CONSTRAINT con_amount CHECK (amount >= 0)
);

-- a few stations can share items in one request
CREATE TABLE IF NOT EXISTS transport_accept (
    id integer PRIMARY KEY GENERATED BY DEFAULT AS IDENTITY,
    request_id integer REFERENCES transport_request(id) ON UPDATE CASCADE,
    to_station_id integer REFERENCES station(id) ON UPDATE CASCADE,
    amount integer,
    accepted_at timestamp DEFAULT CURRENT_TIMESTAMP,
    status integer DEFAULT 1 -- 0 - fulfilled, 1 -- pending, 2 -- unfulfilled
);

CREATE OR REPLACE FUNCTION func_reserve_equipment() RETURNS TRIGGER AS $$
BEGIN
IF TG_OP = 'INSERT' THEN 
    UPDATE equipment
    SET available = available - NEW.amount
    WHERE item_id = NEW.item_id
        AND station_id = NEW.station_id;
ELSE
    IF OLD.received = 'f' AND NEW.received = 't' THEN
        UPDATE reserved_equipment
        SET received_at = now()
        WHERE reservation_id = NEW.reservation_id
            AND item_id = NEW.item_id;
    END IF;

    IF OLD.returned = 'f' AND NEW.returned = 't' THEN
        UPDATE reserved_equipment
        SET returned_at = now()
        WHERE reservation_id = NEW.reservation_id
            AND station_id = NEW.station_id
            AND item_id = NEW.item_id;
    END IF;
END IF;
RETURN NEW;
END
$$ LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION func_request_eq_transport() RETURNS TRIGGER AS $$
BEGIN
IF NEW.available > NEW.maximum THEN
    INSERT INTO transport_request(from_station_id, item_id, amount) VALUES(NEW.station_id, NEW.item_id, NEW.available - NEW.maximum);
    UPDATE equipment SET available = maximum WHERE station_id = NEW.station_id AND item_id = NEW.item_id;
END IF;
RETURN NEW;
END
$$ LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION func_accept_eq_transport() RETURNS TRIGGER AS $$
BEGIN
IF TG_OP = 'INSERT' THEN -- jakaś stanica zamierza przyjąć bezpańskie przedmioty
    UPDATE transport_request SET amount = amount - NEW.amount WHERE id = NEW.request_id;
ELSE
    IF OLD.status <> 2 AND NEW.status = 2 THEN -- jednak rozmyśliła się
        UPDATE transport_request SET amount = amount + NEW.amount WHERE id = NEW.request_id;
    ELSE
        IF OLD.status <> 0 AND NEW.status = 0 THEN
            UPDATE equipment SET available = available + NEW.amount WHERE station_id = NEW.to_station_id;
        END IF;
    END IF;
END IF;
RETURN NEW;
END
$$ LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION func_finish_trip() RETURNS TRIGGER AS $$
BEGIN
IF OLD.finished = 'f' AND NEW.finished = 't' THEN
    UPDATE trip SET finished_at = now() WHERE id = NEW.id;
END IF;
RETURN NEW;
END
$$ LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION func_reject_reservation() RETURNS TRIGGER AS $$
BEGIN
IF OLD.status <> 2 AND NEW.status = 2 THEN
    WITH res_eq AS (
        SELECT station_id, item_id, amount FROM reserved_equipment WHERE reservation_id = NEW.id
    )
    UPDATE equipment
    SET available = available + res_eq.amount
    FROM res_eq
    WHERE equipment.item_id = res_eq.item_id
        AND equipment.station_id = res_eq.station_id;
END IF;
RETURN NEW;
END
$$ LANGUAGE plpgsql;


CREATE OR REPLACE TRIGGER trig_reserve_equipment
AFTER INSERT OR UPDATE ON reserved_equipment FOR EACH ROW EXECUTE PROCEDURE func_reserve_equipment();

CREATE OR REPLACE TRIGGER trig_request_eq_transport
AFTER UPDATE ON equipment FOR EACH ROW EXECUTE PROCEDURE func_request_eq_transport();

CREATE OR REPLACE TRIGGER trig_accept_eq_transport
AFTER INSERT OR UPDATE ON transport_accept FOR EACH ROW EXECUTE PROCEDURE func_accept_eq_transport();

CREATE OR REPLACE TRIGGER trig_finish_trip
AFTER UPDATE ON trip FOR EACH ROW EXECUTE PROCEDURE func_finish_trip();

CREATE OR REPLACE TRIGGER trig_reject_reservation
AFTER UPDATE ON reservation FOR EACH ROW EXECUTE PROCEDURE func_reject_reservation();
