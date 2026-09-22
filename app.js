// PetFinder MVP — client-only demo storage layer.
// NOTE: localStorage is a stand-in for a real backend. Data does not sync
// across devices/browsers. This is intentional for a fast design prototype.

const DB_KEY = "petfinder_db_v1";

function emptyDB() {
  return { owners: {}, pets: {}, events: {}, tags: {} };
}

function loadDB() {
  try {
    const db = JSON.parse(localStorage.getItem(DB_KEY));
    return db ? { ...emptyDB(), ...db } : emptyDB();
  } catch {
    return emptyDB();
  }
}

function saveDB(db) {
  localStorage.setItem(DB_KEY, JSON.stringify(db));
}

function uid(prefix) {
  return prefix + "_" + Math.random().toString(36).slice(2, 9);
}

const Store = {
  createOwner({ username, password, contactName, phone, address }) {
    const db = loadDB();
    const ownerId = uid("owner");
    db.owners[ownerId] = { id: ownerId, username, password, contactName, phone, address };
    saveDB(db);
    return ownerId;
  },

  findOwnerByUsername(username) {
    const db = loadDB();
    return Object.values(db.owners).find(o => o.username === username) || null;
  },

  createPet({ ownerId, name, species, breed, photoDataUrl, notes }) {
    const db = loadDB();
    const petId = uid("pet");
    db.pets[petId] = { id: petId, ownerId, name, species, breed, photoDataUrl, notes, createdAt: Date.now() };
    saveDB(db);
    return petId;
  },

  // --- Tags: blank QR codes an admin pre-generates for a batch of physical
  // tags, before any of them are linked to a pet. The FIRST scan of an
  // unclaimed tag triggers registration; later scans show the pet profile.
  createTag() {
    const db = loadDB();
    const tagId = uid("tag");
    db.tags[tagId] = { id: tagId, claimed: false, petId: null, createdAt: Date.now() };
    saveDB(db);
    return tagId;
  },

  getTag(tagId) {
    const db = loadDB();
    return db.tags[tagId] || null;
  },

  allTags() {
    const db = loadDB();
    return Object.values(db.tags).sort((a, b) => b.createdAt - a.createdAt);
  },

  claimTag(tagId, petId) {
    const db = loadDB();
    const tag = db.tags[tagId];
    if (!tag) return;
    tag.claimed = true;
    tag.petId = petId;
    saveDB(db);
  },

  getPet(petId) {
    const db = loadDB();
    return db.pets[petId] || null;
  },

  getOwner(ownerId) {
    const db = loadDB();
    return db.owners[ownerId] || null;
  },

  petsByOwner(ownerId) {
    const db = loadDB();
    return Object.values(db.pets).filter(p => p.ownerId === ownerId);
  },

  // A "scan event" is created when a finder opts in to share location
  // (one-time or live). Nothing is written here without explicit consent
  // captured by the browser's geolocation permission prompt.
  recordScanEvent({ petId, mode, lat, lng, expiresAt }) {
    const db = loadDB();
    const eventId = uid("evt");
    db.events[eventId] = {
      id: eventId,
      petId,
      mode, // "one-time" | "live"
      lat,
      lng,
      createdAt: Date.now(),
      updatedAt: Date.now(),
      expiresAt: expiresAt || null,
      active: mode === "live",
    };
    saveDB(db);
    return eventId;
  },

  updateLiveEvent(eventId, { lat, lng }) {
    const db = loadDB();
    const evt = db.events[eventId];
    if (!evt) return;
    evt.lat = lat;
    evt.lng = lng;
    evt.updatedAt = Date.now();
    saveDB(db);
  },

  stopLiveEvent(eventId) {
    const db = loadDB();
    const evt = db.events[eventId];
    if (!evt) return;
    evt.active = false;
    saveDB(db);
  },

  eventsByOwner(ownerId) {
    const db = loadDB();
    const petIds = new Set(this.petsByOwner(ownerId).map(p => p.id));
    return Object.values(db.events)
      .filter(e => petIds.has(e.petId))
      .sort((a, b) => b.createdAt - a.createdAt);
  },
};

function qs(name) {
  return new URLSearchParams(location.search).get(name);
}
