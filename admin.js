// Admin Panel JavaScript

// Admin authentication
const ADMIN_CREDENTIALS = {
  username: 'admin',
  password: 'backtobase2024'
};

// Check if user is authenticated
function checkAdminAuth() {
  const isAuthenticated = localStorage.getItem('btb_admin_auth') === 'true';
  if (!isAuthenticated && !window.location.pathname.includes('admin-login')) {
    window.location.href = 'admin-login.html';
  }
}

// Login form handler
function initAdminLogin() {
  const loginForm = document.getElementById('admin-login-form');
  if (!loginForm) return;

  loginForm.addEventListener('submit', (e) => {
    e.preventDefault();
    
    const username = document.getElementById('admin-username').value;
    const password = document.getElementById('admin-password').value;
    const errorDiv = document.getElementById('admin-login-error');
    
    if (username === ADMIN_CREDENTIALS.username && password === ADMIN_CREDENTIALS.password) {
      localStorage.setItem('btb_admin_auth', 'true');
      window.location.href = 'admin.html';
    } else {
      errorDiv.textContent = 'Invalid username or password';
      errorDiv.style.display = 'block';
    }
  });
}

// Logout function
function adminLogout() {
  localStorage.removeItem('btb_admin_auth');
  window.location.href = 'admin-login.html';
}

// Navigation between sections
function showSection(sectionName) {
  // Hide all sections
  document.querySelectorAll('.admin-section').forEach(section => {
    section.style.display = 'none';
  });
  
  // Remove active class from all nav buttons
  document.querySelectorAll('.admin-nav-btn').forEach(btn => {
    btn.classList.remove('active');
  });
  
  // Show selected section
  const targetSection = document.getElementById(`${sectionName}-section`);
  if (targetSection) {
    targetSection.style.display = 'block';
  }
  
  // Add active class to clicked button
  const activeBtn = document.querySelector(`[data-section="${sectionName}"]`);
  if (activeBtn) {
    activeBtn.classList.add('active');
  }
  
  // Load section data
  loadSectionData(sectionName);
}

// Load data for specific section
function loadSectionData(sectionName) {
  switch(sectionName) {
    case 'homepage':
      loadHomepageData();
      break;
    case 'floorplan':
      loadFloorplanData();
      initFloorplanImageUpload();
      break;
    case 'rooms':
      loadRoomsData();
      break;
    case 'massage':
      loadMassageData();
      break;
    case 'yoga':
      loadYogaData();
      break;
    case 'content':
      loadContentData();
      break;
    case 'images':
      loadImagesData();
      break;
    case 'bookings':
      loadBookingsData();
      initBookingsFilters();
      break;
    case 'calendar':
      loadCalendarData();
      loadBlockedDates();
      initCalendarBlocking();
      break;
    case 'dashboard':
      updateDashboardStats();
      break;
  }
}

// Homepage management
async function loadHomepageData() {
  // Load existing homepage content from Content settings
  try {
    const formData = new FormData();
    formData.append('action', 'get_content');
    
    const response = await fetch('api.php', {
      method: 'POST',
      body: formData
    });
    
    if (response.ok) {
      const result = await response.json();
      if (result.success && result.data) {
        // Populate fields with existing content
        document.getElementById('homepage-main-description').value = result.data.homepageDescription || '';
        document.getElementById('homepage-main-subtitle').value = result.data.homepageSubtitle || '';
        document.getElementById('hero-image-url').value = result.data.heroImageUrl || '';
        document.getElementById('hero2-image-url').value = result.data.hero2ImageUrl || '';
      }
    }
  } catch (error) {
    console.log('Failed to load homepage data');
  }
}

// Floor Plan management
async function loadFloorplanData() {
  // Load existing floorplan content
  try {
    console.log('Loading floorplan data...');
    const formData = new FormData();
    formData.append('action', 'get_floorplan');
    
    const response = await fetch('api.php', {
      method: 'POST',
      body: formData
    });
    
    console.log('Response status:', response.status);
    
    if (response.ok) {
      const result = await response.json();
      console.log('API response:', result);
      
      if (result.success && result.data) {
        console.log('Populating fields with data:', result.data);
        // Populate fields with existing content
        // API returns fields with underscores, but we need to map them to the correct field names
        console.log('Setting basement-subtitle to:', result.data.basement_subtitle);
        document.getElementById('basement-subtitle').value = result.data.basement_subtitle || '';
        console.log('Setting basement-description to:', result.data.basement_description);
        document.getElementById('basement-description').value = result.data.basement_description || '';
        console.log('Setting basement-image-url to:', result.data.basement_image_url);
        if (result.data.basement_image_url) {
          // Show preview of existing image
          const preview = document.getElementById('basement-image-preview');
          const pathDisplay = document.getElementById('basement-image-path');
          if (preview && pathDisplay) {
            const img = document.createElement('img');
            img.src = result.data.basement_image_url + '?v=' + Date.now();
            preview.innerHTML = '';
            preview.appendChild(img);
            preview.style.display = 'block';
            pathDisplay.textContent = result.data.basement_image_url;
            pathDisplay.style.display = 'block';
          }
        }
        console.log('Setting ground-subtitle to:', result.data.ground_subtitle);
        document.getElementById('ground-subtitle').value = result.data.ground_subtitle || '';
        console.log('Setting ground-description to:', result.data.ground_description);
        document.getElementById('ground-description').value = result.data.ground_description || '';
        // Universal: use ground_image_url (fallback to ground_queen_image for compatibility)
        const groundImage = result.data.ground_image_url || result.data.ground_queen_image || '';
        console.log('Setting ground-image-url to:', groundImage);
        if (groundImage) {
          // Show preview of existing image
          const preview = document.getElementById('ground-image-preview');
          const pathDisplay = document.getElementById('ground-image-path');
          if (preview && pathDisplay) {
            const img = document.createElement('img');
            img.src = groundImage + '?v=' + Date.now();
            preview.innerHTML = '';
            preview.appendChild(img);
            preview.style.display = 'block';
            pathDisplay.textContent = groundImage;
            pathDisplay.style.display = 'block';
          }
        }
        // ground-twin-image field removed
        console.log('Setting loft-subtitle to:', result.data.loft_subtitle);
        document.getElementById('loft-subtitle').value = result.data.loft_subtitle || '';
        console.log('Setting loft-description to:', result.data.loft_description);
        document.getElementById('loft-description').value = result.data.loft_description || '';
        console.log('Setting loft-image-url to:', result.data.loft_image_url);
        if (result.data.loft_image_url) {
          // Show preview of existing image
          const preview = document.getElementById('loft-image-preview');
          const pathDisplay = document.getElementById('loft-image-path');
          if (preview && pathDisplay) {
            const img = document.createElement('img');
            img.src = result.data.loft_image_url + '?v=' + Date.now();
            preview.innerHTML = '';
            preview.appendChild(img);
            preview.style.display = 'block';
            pathDisplay.textContent = result.data.loft_image_url;
            pathDisplay.style.display = 'block';
          }
        }
        console.log('Fields populated successfully');
      } else {
        console.log('No data received or API failed');
      }
    } else {
      console.log('Response not OK:', response.status);
    }
  } catch (error) {
    console.log('Failed to load floorplan data:', error);
  }
}

// Rooms management
function loadRoomsData() {
  const roomsList = document.getElementById('rooms-list');
  if (!roomsList) return;
  
  const rooms = getStoredData('btb_rooms') || getDefaultRooms();
  
  roomsList.innerHTML = rooms.map((room, index) => `
    <div class="admin-room-card" style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; margin-bottom: 12px;">
      <div style="display: flex; justify-content: space-between; align-items: start;">
        <div style="flex: 1;">
          <h4 style="margin: 0 0 8px 0; color: #2d3748;">${room.name}</h4>
          <p style="margin: 0 0 4px 0; color: #4a5568;">Price: ${room.price} CAD/night</p>
          <p style="margin: 0 0 4px 0; color: #4a5568;">Capacity: ${room.capacity} guests</p>
          <p style="margin: 0; color: #718096; font-size: 14px;">${room.description}</p>
        </div>
        <div style="display: flex; gap: 8px; margin-left: 16px;">
          <button class="admin-btn admin-btn-secondary" onclick="editRoom(${index})">Edit</button>
          <button class="admin-btn admin-btn-danger" onclick="deleteRoom(${index})">Delete</button>
        </div>
      </div>
    </div>
  `).join('');
}

function getDefaultRooms() {
  return [
    {
      name: 'Basement — Queen bed',
      price: 140,
      capacity: 2,
      type: 'queen',
      description: 'Cozy room next to the home cinema and sauna. Perfect for two.'
    },
    {
      name: 'Ground floor — Queen bed',
      price: 130,
      capacity: 2,
      type: 'queen',
      description: 'Compact, bright room with access to the fireplace lounge.'
    },
    {
      name: 'Ground floor — Twin beds',
      price: 125,
      capacity: 2,
      type: 'twin',
      description: 'Great for friends or colleagues. Close to the kitchen and massage hall.'
    },
    {
      name: 'Second floor (entire) — Queen bed',
      price: 210,
      capacity: 2,
      type: 'suite',
      description: 'Separate kitchen and shower, study, and a balcony with lake view.'
    }
  ];
}

// Massage management
function loadMassageData() {
  const massageList = document.getElementById('massage-list');
  if (!massageList) return;
  
  const massages = getStoredData('btb_massage_services') || getDefaultMassageServices();
  
  massageList.innerHTML = massages.map((service, index) => `
    <div class="admin-service-card" style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; margin-bottom: 12px;">
      <div style="display: flex; justify-content: space-between; align-items: start;">
        <div style="flex: 1;">
          <h4 style="margin: 0 0 8px 0; color: #2d3748;">${service.name}</h4>
          <p style="margin: 0 0 4px 0; color: #4a5568;">Duration: ${service.duration} minutes</p>
          <p style="margin: 0 0 4px 0; color: #4a5568;">Price: ${service.price} CAD</p>
          <p style="margin: 0; color: #718096; font-size: 14px;">${service.description}</p>
        </div>
        <div style="display: flex; gap: 8px; margin-left: 16px;">
          <button class="admin-btn admin-btn-secondary" onclick="editMassage(${index})">Edit</button>
          <button class="admin-btn admin-btn-danger" onclick="deleteMassage(${index})">Delete</button>
        </div>
      </div>
    </div>
  `).join('');
}

function getDefaultMassageServices() {
  return [
    {
      name: 'Relaxing Massage',
      duration: 60,
      price: 110,
      type: 'relaxing',
      description: 'This gentle massage, perfect for those who want to unwind and restore their energy.'
    },
    {
      name: 'Relaxing Massage',
      duration: 90,
      price: 160,
      type: 'relaxing',
      description: 'Extended relaxing massage session for deeper relaxation.'
    },
    {
      name: 'Deep Tissue Massage',
      duration: 60,
      price: 120,
      type: 'deep-tissue',
      description: 'For targeted relief of muscle tension and pain.'
    },
    {
      name: 'Deep Tissue Massage',
      duration: 90,
      price: 170,
      type: 'deep-tissue',
      description: 'Extended deep tissue massage for chronic issues.'
    },
    {
      name: 'Reiki Energy Healing',
      duration: 15,
      price: 25,
      type: 'reiki',
      description: 'Gentle energy healing technique that promotes relaxation.'
    },
    {
      name: 'Reiki Energy Healing',
      duration: 30,
      price: 50,
      type: 'reiki',
      description: 'Extended Reiki session for deeper energy work.'
    }
  ];
}

// Yoga management
function loadYogaData() {
  const yogaList = document.getElementById('yoga-list');
  if (!yogaList) return;
  
  const yogaServices = getStoredData('btb_yoga_services') || getDefaultYogaServices();
  
  yogaList.innerHTML = yogaServices.map((service, index) => `
    <div class="admin-service-card" style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; margin-bottom: 12px;">
      <div style="display: flex; justify-content: space-between; align-items: start;">
        <div style="flex: 1;">
          <h4 style="margin: 0 0 8px 0; color: #2d3748;">${service.name}</h4>
          <p style="margin: 0 0 4px 0; color: #4a5568;">Duration: ${service.duration} minutes</p>
          <p style="margin: 0 0 4px 0; color: #4a5568;">Price: ${service.price} CAD</p>
          <p style="margin: 0; color: #718096; font-size: 14px;">${service.description}</p>
        </div>
        <div style="display: flex; gap: 8px; margin-left: 16px;">
          <button class="admin-btn admin-btn-secondary" onclick="editYoga(${index})">Edit</button>
          <button class="admin-btn admin-btn-danger" onclick="deleteYoga(${index})">Delete</button>
        </div>
      </div>
    </div>
  `).join('');
}

function getDefaultYogaServices() {
  return [
    {
      name: 'Individual Session',
      duration: 60,
      price: 90,
      type: 'individual',
      description: 'One-on-one yoga session in the forest with an experienced instructor.'
    },
    {
      name: 'Couple Session',
      duration: 60,
      price: 120,
      type: 'couple',
      description: 'Yoga session for couples in a peaceful forest setting.'
    },
    {
      name: 'Group Training',
      duration: 60,
      price: 0,
      type: 'group',
      description: 'Group yoga sessions - price and time arranged individually.'
    }
  ];
}

// Content management
async function loadContentData() {
  // Try to load from API first
  try {
    const formData = new FormData();
    formData.append('action', 'get_content');
    
    const response = await fetch('api.php', {
      method: 'POST',
      body: formData
    });
    
    if (response.ok) {
      const result = await response.json();
      if (result.success && result.data) {
        const content = result.data;
        
        document.getElementById('homepage-description').value = content.homepageDescription || '';
        document.getElementById('homepage-subtitle').value = content.homepageSubtitle || '';
        document.getElementById('contact-phone').value = content.contactPhone || '+1 (555) 123‑4567';
        document.getElementById('contact-email').value = content.contactEmail || 'hello@backtobase.example';
        document.getElementById('contact-address').value = content.contactAddress || 'British Columbia, Canada';
        return;
      }
    }
  } catch (error) {
    console.log('Failed to load from API, using localStorage');
  }
  
  // Fallback to localStorage
  const content = getStoredData('btb_content') || getDefaultContent();
  
  document.getElementById('homepage-description').value = content.homepageDescription || '';
  document.getElementById('homepage-subtitle').value = content.homepageSubtitle || '';
  document.getElementById('contact-phone').value = content.contactPhone || '+1 (555) 123‑4567';
  document.getElementById('contact-email').value = content.contactEmail || 'hello@backtobase.example';
  document.getElementById('contact-address').value = content.contactAddress || 'British Columbia, Canada';
}

function getDefaultContent() {
  return {
    homepageDescription: 'Back to Base is a countryside guesthouse in Nelson, British Columbia, where you can rent a room or book the entire house for a vacation, retreat, or wellness getaway. Guests can restore their energy with a relaxing massage and enjoy comfortable accommodation surrounded by mountains and forest.',
    homepageSubtitle: 'Our cozy rooms and inspiring atmosphere make this the perfect place for solitude, meditation, yoga retreats, or simply a peaceful holiday in nature.',
    contactPhone: '+1 (555) 123‑4567',
    contactEmail: 'hello@backtobase.example',
    contactAddress: 'British Columbia, Canada'
  };
}

// Images management
function loadImagesData() {
  const imagesContainer = document.getElementById('current-images');
  if (!imagesContainer) return;
  
  const images = getStoredData('btb_images') || [];
  
  imagesContainer.innerHTML = images.map((image, index) => `
    <div class="admin-image-item" style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; text-align: center;">
      <img src="${image.url}" alt="${image.name}" style="max-width: 150px; max-height: 150px; border-radius: 4px; margin-bottom: 8px;">
      <p style="margin: 0 0 8px 0; font-size: 14px; color: #2d3748;">${image.name}</p>
      <button class="admin-btn admin-btn-danger" onclick="deleteImage(${index})">Delete</button>
    </div>
  `).join('');
}

// Utility functions
function getStoredData(key) {
  try {
    const data = localStorage.getItem(key);
    return data ? JSON.parse(data) : null;
  } catch (e) {
    return null;
  }
}

function setStoredData(key, data) {
  try {
    localStorage.setItem(key, JSON.stringify(data));
    return true;
  } catch (e) {
    return false;
  }
}

function showStatus(message, type = 'success') {
  const statusDiv = document.createElement('div');
  statusDiv.className = `admin-status ${type}`;
  statusDiv.textContent = message;
  
  const container = document.querySelector('.admin-container');
  container.insertBefore(statusDiv, container.firstChild);
  
  setTimeout(() => {
    statusDiv.remove();
  }, 3000);
}

// Form handlers
function initAdminForms() {
  // Add room form
  const addRoomForm = document.getElementById('add-room-form');
  if (addRoomForm) {
    addRoomForm.addEventListener('submit', (e) => {
      e.preventDefault();
      
      const room = {
        name: document.getElementById('room-name').value,
        price: parseInt(document.getElementById('room-price').value),
        capacity: parseInt(document.getElementById('room-capacity').value),
        type: document.getElementById('room-type').value,
        description: document.getElementById('room-description').value
      };
      
      const rooms = getStoredData('btb_rooms') || getDefaultRooms();
      rooms.push(room);
      setStoredData('btb_rooms', rooms);
      
      showStatus('Room added successfully!');
      loadRoomsData();
      addRoomForm.reset();
    });
  }
  
  // Add massage form
  const addMassageForm = document.getElementById('add-massage-form');
  if (addMassageForm) {
    addMassageForm.addEventListener('submit', (e) => {
      e.preventDefault();
      
      const service = {
        name: document.getElementById('massage-name').value,
        duration: parseInt(document.getElementById('massage-duration').value),
        price: parseInt(document.getElementById('massage-price').value),
        type: document.getElementById('massage-type').value,
        description: document.getElementById('massage-description').value
      };
      
      const services = getStoredData('btb_massage_services') || getDefaultMassageServices();
      services.push(service);
      setStoredData('btb_massage_services', services);
      
      showStatus('Massage service added successfully!');
      loadMassageData();
      addMassageForm.reset();
    });
  }
  
  // Add yoga form
  const addYogaForm = document.getElementById('add-yoga-form');
  if (addYogaForm) {
    addYogaForm.addEventListener('submit', (e) => {
      e.preventDefault();
      
      const service = {
        name: document.getElementById('yoga-name').value,
        duration: parseInt(document.getElementById('yoga-duration').value),
        price: parseInt(document.getElementById('yoga-price').value),
        type: document.getElementById('yoga-type').value,
        description: document.getElementById('yoga-description').value
      };
      
      const services = getStoredData('btb_yoga_services') || getDefaultYogaServices();
      services.push(service);
      setStoredData('btb_yoga_services', services);
      
      showStatus('Yoga service added successfully!');
      loadYogaData();
      addYogaForm.reset();
    });
  }
  
  // Save content
  const saveContentBtn = document.getElementById('save-content');
  if (saveContentBtn) {
    saveContentBtn.addEventListener('click', async () => {
      const currentContent = getStoredData('btb_content') || getDefaultContent();
      const content = {
        ...currentContent,
        homepageDescription: document.getElementById('homepage-description').value,
        homepageSubtitle: document.getElementById('homepage-subtitle').value
      };
      
      // Try to save to server first
      try {
        const saved = await saveContentToServer(content);
        if (saved) {
          showStatus('Content saved successfully!');
          return;
        }
      } catch (error) {
        console.log('Server save failed, saving to localStorage');
      }
      
      // Fallback to localStorage
      setStoredData('btb_content', content);
      showStatus('Content saved successfully!');
    });
  }
  
  // Save contact
  const saveContactBtn = document.getElementById('save-contact');
  if (saveContactBtn) {
    saveContactBtn.addEventListener('click', async () => {
      const content = getStoredData('btb_content') || getDefaultContent();
      content.contactPhone = document.getElementById('contact-phone').value;
      content.contactEmail = document.getElementById('contact-email').value;
      content.contactAddress = document.getElementById('contact-address').value;
      
      // Try to save to server first
      try {
        const saved = await saveContentToServer(content);
        if (saved) {
          showStatus('Contact information saved successfully!');
          return;
        }
      } catch (error) {
        console.log('Server save failed, saving to localStorage');
      }
      
      // Fallback to localStorage
      setStoredData('btb_content', content);
      showStatus('Contact information saved successfully!');
    });
  }
}

// Save content to server via API
async function saveContentToServer(content) {
  try {
    const formData = new FormData();
    formData.append('action', 'save_content');
    formData.append('homepage_description', content.homepageDescription || '');
    formData.append('homepage_subtitle', content.homepageSubtitle || '');
    formData.append('contact_phone', content.contactPhone || '');
    formData.append('contact_email', content.contactEmail || '');
    formData.append('contact_address', content.contactAddress || '');
    formData.append('hero_image_url', content.heroImageUrl || '');
    formData.append('hero2_image_url', content.hero2ImageUrl || '');
    
    const response = await fetch('api.php', {
      method: 'POST',
      body: formData
    });
    
    const result = await response.json();
    return result.success;
  } catch (error) {
    console.error('Error saving to server:', error);
    return false;
  }
}

// Image upload
function initImageUpload() {
  const uploadArea = document.getElementById('image-upload-area');
  const fileInput = document.getElementById('image-upload');
  const previewContainer = document.getElementById('image-preview-container');
  
  if (!uploadArea || !fileInput) return;
  
  uploadArea.addEventListener('click', () => fileInput.click());
  
  uploadArea.addEventListener('dragover', (e) => {
    e.preventDefault();
    uploadArea.classList.add('dragover');
  });
  
  uploadArea.addEventListener('dragleave', () => {
    uploadArea.classList.remove('dragover');
  });
  
  uploadArea.addEventListener('drop', (e) => {
    e.preventDefault();
    uploadArea.classList.remove('dragover');
    handleFiles(e.dataTransfer.files);
  });
  
  fileInput.addEventListener('change', (e) => {
    handleFiles(e.target.files);
  });
  
  function handleFiles(files) {
    Array.from(files).forEach(file => {
      if (file.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onload = (e) => {
          const imageDiv = document.createElement('div');
          imageDiv.style.cssText = 'display: inline-block; margin: 8px; text-align: center;';
          imageDiv.innerHTML = `
            <img src="${e.target.result}" style="max-width: 150px; max-height: 150px; border-radius: 4px; margin-bottom: 8px;">
            <p style="margin: 0; font-size: 12px; color: #4a5568;">${file.name}</p>
          `;
          previewContainer.appendChild(imageDiv);
        };
        reader.readAsDataURL(file);
      }
    });
  }
}

// Initialize floorplan image upload functionality
function initFloorplanImageUpload() {
  const uploadConfigs = [
    {
      buttonId: 'basement-upload-btn',
      inputId: 'basement-image-upload',
      previewId: 'basement-image-preview',
      pathId: 'basement-image-path',
      imageType: 'basement'
    },
    {
      buttonId: 'ground-upload-btn',
      inputId: 'ground-image-upload',
      previewId: 'ground-image-preview',
      pathId: 'ground-image-path',
      imageType: 'ground'
    },
    {
      buttonId: 'loft-upload-btn',
      inputId: 'loft-image-upload',
      previewId: 'loft-image-preview',
      pathId: 'loft-image-path',
      imageType: 'loft'
    }
  ];

  uploadConfigs.forEach(config => {
    const uploadBtn = document.getElementById(config.buttonId);
    const fileInput = document.getElementById(config.inputId);
    const preview = document.getElementById(config.previewId);
    const pathDisplay = document.getElementById(config.pathId);

    if (uploadBtn && fileInput) {
      uploadBtn.addEventListener('click', () => {
        fileInput.click();
      });

      fileInput.addEventListener('change', async (e) => {
        const file = e.target.files[0];
        if (file) {
          await uploadFloorplanImage(file, config.imageType, preview, pathDisplay);
        }
      });
    }
  });
}

// Upload floorplan image function
async function uploadFloorplanImage(file, imageType, previewElement, pathElement) {
  const formData = new FormData();
  formData.append('image', file);
  formData.append('image_type', imageType);

  try {
    console.log(`Uploading ${imageType} image:`, file.name);
    
    const response = await fetch('upload_image.php', {
      method: 'POST',
      body: formData
    });

    const result = await response.json();
    // Some endpoints wrap payload under data; normalize
    const payload = result && result.data ? result.data : result;
    const filepath = payload && payload.filepath ? payload.filepath : '';
    
    if (result.success) {
      console.log('Image uploaded successfully:', result);
      
      // Show preview
      const img = document.createElement('img');
      img.src = filepath + '?v=' + Date.now();
      previewElement.innerHTML = '';
      previewElement.appendChild(img);
      previewElement.style.display = 'block';
      
      // Show path
      pathElement.textContent = filepath;
      pathElement.style.display = 'block';
      
      // Update localStorage for immediate site update
      const storedData = localStorage.getItem('btb_floorplan_settings');
      if (storedData) {
        const data = JSON.parse(storedData);
        // Universal field name mapping - all use {section}_image_url format
        const fieldName = imageType + '_image_url';
        data[fieldName] = filepath;
        localStorage.setItem('btb_floorplan_settings', JSON.stringify(data));
        console.log('Updated localStorage with new image path:', filepath);
      }
      
      showStatus(`${imageType.charAt(0).toUpperCase() + imageType.slice(1)} image uploaded successfully!`);
      
      // Force reload of floor plan data to get updated paths
      setTimeout(() => {
        loadFloorplanData();
      }, 1000);
      
    } else {
      console.error('Upload failed:', result.error);
      showStatus(`Upload failed: ${result.error}`, 'error');
    }
  } catch (error) {
    console.error('Upload error:', error);
    showStatus('Upload failed: ' + error.message, 'error');
  }
}

// Initialize admin panel
document.addEventListener('DOMContentLoaded', () => {
  // Check authentication
  checkAdminAuth();
  
  // Initialize login form
  initAdminLogin();
  
  // Initialize navigation
  document.querySelectorAll('.admin-nav-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const section = btn.getAttribute('data-section');
      showSection(section);
    });
  });
  
  // Initialize logout
  const logoutBtn = document.getElementById('admin-logout');
  if (logoutBtn) {
    logoutBtn.addEventListener('click', adminLogout);
  }
  
  // Initialize save homepage button
  const saveHomepageBtn = document.getElementById('save-homepage');
  if (saveHomepageBtn) {
    saveHomepageBtn.addEventListener('click', async () => {
      const homepageDescription = document.getElementById('homepage-main-description').value;
      const homepageSubtitle = document.getElementById('homepage-main-subtitle').value;
      const heroImageUrl = document.getElementById('hero-image-url').value;
      const hero2ImageUrl = document.getElementById('hero2-image-url').value;
      
      const currentContent = getStoredData('btb_content') || getDefaultContent();
      const content = {
        ...currentContent,
        homepageDescription: homepageDescription,
        homepageSubtitle: homepageSubtitle,
        heroImageUrl: heroImageUrl,
        hero2ImageUrl: hero2ImageUrl
      };
      
      try {
        const saved = await saveContentToServer(content);
        if (saved) {
          showStatus('Homepage content saved successfully!');
          return;
        }
      } catch (error) {
        console.log('Server save failed, saving to localStorage');
      }
      
      setStoredData('btb_content', content);
      showStatus('Homepage content saved successfully!');
    });
  }
  
  // Initialize save floorplan button
  const saveFloorplanBtn = document.getElementById('save-floorplan');
  if (saveFloorplanBtn) {
    saveFloorplanBtn.addEventListener('click', async () => {
      console.log('Saving floorplan data...');
      // Read current image paths from localStorage or visible path labels to avoid overwriting with empty values
      const stored = localStorage.getItem('btb_floorplan_settings');
      const storedJson = stored ? JSON.parse(stored) : {};
      const basementPathEl = document.getElementById('basement-image-path');
      const groundPathEl = document.getElementById('ground-image-path');
      const loftPathEl = document.getElementById('loft-image-path');

      // Universal: use consistent field names (with fallback for compatibility)
      const currentBasementImage = (basementPathEl && basementPathEl.textContent) || storedJson.basement_image_url || '';
      const currentGroundImage = (groundPathEl && groundPathEl.textContent) || storedJson.ground_image_url || storedJson.ground_queen_image || '';
      const currentLoftImage = (loftPathEl && loftPathEl.textContent) || storedJson.loft_image_url || '';

      const floorplanData = {
        basementSubtitle: document.getElementById('basement-subtitle').value,
        basementDescription: document.getElementById('basement-description').value,
        basementImageUrl: currentBasementImage,
        groundSubtitle: document.getElementById('ground-subtitle').value,
        groundDescription: document.getElementById('ground-description').value,
        groundQueenImage: currentGroundImage,
        groundTwinImage: '',
        loftSubtitle: document.getElementById('loft-subtitle').value,
        loftDescription: document.getElementById('loft-description').value,
        loftImageUrl: currentLoftImage
      };
      
      console.log('Floorplan data to save:', floorplanData);
      
      try {
        const formData = new FormData();
        formData.append('action', 'save_floorplan');
        Object.entries(floorplanData).forEach(([key, value]) => {
          formData.append(key, value || '');
        });
        
        console.log('Sending request to API...');
        const response = await fetch('api.php', {
          method: 'POST',
          body: formData
        });
        
        console.log('Response status:', response.status);
        const result = await response.json();
        console.log('API response:', result);
        
        if (result.success) {
          // Save to localStorage for immediate update on main site
          // Convert camelCase to underscore format for consistency with API
          // Universal: use consistent field names for all sections
          const localStorageData = {
            basement_subtitle: floorplanData.basementSubtitle,
            basement_description: floorplanData.basementDescription,
            basement_image_url: floorplanData.basementImageUrl,
            ground_subtitle: floorplanData.groundSubtitle,
            ground_description: floorplanData.groundDescription,
            ground_image_url: floorplanData.groundQueenImage, // Universal field name
            ground_queen_image: floorplanData.groundQueenImage, // Keep for compatibility
            ground_twin_image: floorplanData.groundTwinImage,
            loft_subtitle: floorplanData.loftSubtitle,
            loft_description: floorplanData.loftDescription,
            loft_image_url: floorplanData.loftImageUrl
          };
          localStorage.setItem('btb_floorplan_settings', JSON.stringify(localStorageData));
          console.log('Data saved to localStorage');
          showStatus('Floor plan content saved successfully!');
          return;
        } else {
          console.log('API returned error:', result.error);
        }
      } catch (error) {
        console.log('Server save failed:', error);
        // Save to localStorage even if server fails
        // Convert camelCase to underscore format for consistency with API
        // Universal: use consistent field names for all sections
        const localStorageData = {
          basement_subtitle: floorplanData.basementSubtitle,
          basement_description: floorplanData.basementDescription,
          basement_image_url: floorplanData.basementImageUrl,
          ground_subtitle: floorplanData.groundSubtitle,
          ground_description: floorplanData.groundDescription,
          ground_image_url: floorplanData.groundQueenImage, // Universal field name
          ground_queen_image: floorplanData.groundQueenImage, // Keep for compatibility
          ground_twin_image: floorplanData.groundTwinImage,
          loft_subtitle: floorplanData.loftSubtitle,
          loft_description: floorplanData.loftDescription,
          loft_image_url: floorplanData.loftImageUrl
        };
        localStorage.setItem('btb_floorplan_settings', JSON.stringify(localStorageData));
        console.log('Data saved to localStorage as fallback');
      }
      
      showStatus('Floor plan content saved successfully!');
    });
  }
  
  // Initialize forms
  initAdminForms();
  
  // Initialize image upload
  initImageUpload();
  
  // Load initial data
  loadSectionData('dashboard');
});

// ==========================================
// BOOKINGS MANAGEMENT
// ==========================================

// Load bookings data
async function loadBookingsData() {
  const loadingEl = document.getElementById('bookings-loading');
  const listEl = document.getElementById('bookings-list');
  const emptyEl = document.getElementById('bookings-empty');
  
  if (loadingEl) loadingEl.style.display = 'block';
  if (listEl) listEl.style.display = 'none';
  if (emptyEl) emptyEl.style.display = 'none';
  
  try {
    // Get filters
    const status = document.getElementById('bookings-filter-status')?.value || '';
    const room = document.getElementById('bookings-filter-room')?.value || '';
    const dateFrom = document.getElementById('bookings-filter-date-from')?.value || '';
    const dateTo = document.getElementById('bookings-filter-date-to')?.value || '';
    
    // Build query params
    const params = new URLSearchParams({ action: 'get_bookings' });
    if (status) params.append('status', status);
    if (room) params.append('room_name', room);
    if (dateFrom) params.append('date_from', dateFrom);
    if (dateTo) params.append('date_to', dateTo);
    
    const response = await fetch('api.php?' + params.toString(), {
      method: 'GET'
    });
    
    if (!response.ok) {
      throw new Error('Failed to load bookings');
    }
    
    const result = await response.json();
    
    if (loadingEl) loadingEl.style.display = 'none';
    
    if (result.success && result.data?.bookings && result.data.bookings.length > 0) {
      if (listEl) {
        listEl.style.display = 'block';
        renderBookingsList(result.data.bookings);
      }
    } else {
      if (emptyEl) emptyEl.style.display = 'block';
    }
  } catch (error) {
    console.error('Load bookings error:', error);
    if (loadingEl) loadingEl.style.display = 'none';
    if (emptyEl) emptyEl.style.display = 'block';
    showStatus('Failed to load bookings: ' + error.message, 'error');
  }
}

// Render bookings list
function renderBookingsList(bookings) {
  const listEl = document.getElementById('bookings-list');
  if (!listEl) return;
  
  listEl.innerHTML = '';
  
  bookings.forEach(booking => {
    const card = document.createElement('div');
    card.className = 'booking-card';
    
    const statusClass = booking.status || 'pending';
    const paymentStatusClass = booking.payment_status || 'pending';
    
    card.innerHTML = `
      <div class="booking-card-header">
        <div>
          <h3 style="margin: 0 0 8px 0;">Booking #${booking.id || '—'}</h3>
          <div style="display: flex; align-items: center;">
            <span class="booking-status-badge ${statusClass}">${statusClass}</span>
            <span class="payment-status-badge ${paymentStatusClass}">${paymentStatusClass}</span>
          </div>
        </div>
        <div style="text-align: right;">
          <div style="font-size: 12px; color: #718096; margin-bottom: 4px;">Confirmation Code</div>
          <div style="font-family: monospace; font-weight: 600;">${booking.confirmation_code || '—'}</div>
        </div>
      </div>
      
      <div class="booking-details-grid">
        <div class="booking-detail-item">
          <div class="booking-detail-label">Room</div>
          <div class="booking-detail-value">${escapeHtml(booking.room_name || '—')}</div>
        </div>
        <div class="booking-detail-item">
          <div class="booking-detail-label">Check-in</div>
          <div class="booking-detail-value">${formatDate(booking.checkin_date)}</div>
        </div>
        <div class="booking-detail-item">
          <div class="booking-detail-label">Check-out</div>
          <div class="booking-detail-value">${formatDate(booking.checkout_date)}</div>
        </div>
        <div class="booking-detail-item">
          <div class="booking-detail-label">Guests</div>
          <div class="booking-detail-value">${booking.guests_count || '—'}</div>
        </div>
        <div class="booking-detail-item">
          <div class="booking-detail-label">Pets</div>
          <div class="booking-detail-value">${booking.pets ? 'Yes' : 'No'}</div>
        </div>
        <div class="booking-detail-item">
          <div class="booking-detail-label">Total Amount</div>
          <div class="booking-detail-value">${booking.currency || 'CAD'} ${parseFloat(booking.total_amount || 0).toFixed(2)}</div>
        </div>
        <div class="booking-detail-item">
          <div class="booking-detail-label">Guest Name</div>
          <div class="booking-detail-value">${escapeHtml(booking.guest_name || '—')}</div>
        </div>
        <div class="booking-detail-item">
          <div class="booking-detail-label">Email</div>
          <div class="booking-detail-value">${escapeHtml(booking.email || '—')}</div>
        </div>
        <div class="booking-detail-item">
          <div class="booking-detail-label">Phone</div>
          <div class="booking-detail-value">${escapeHtml(booking.phone || '—')}</div>
        </div>
        <div class="booking-detail-item">
          <div class="booking-detail-label">Created</div>
          <div class="booking-detail-value">${formatDateTime(booking.created_at)}</div>
        </div>
      </div>
      
      <div class="booking-actions">
        ${booking.status === 'pending' ? `
          <button class="admin-btn admin-btn-primary" onclick="confirmBooking(${booking.id})">Confirm</button>
          <button class="admin-btn admin-btn-danger" onclick="cancelBooking(${booking.id})">Cancel</button>
        ` : ''}
        ${booking.status === 'confirmed' && booking.payment_status !== 'paid' ? `
          <button class="admin-btn admin-btn-danger" onclick="cancelBooking(${booking.id})">Cancel</button>
        ` : ''}
        ${booking.status === 'confirmed' ? `
          <button class="admin-btn admin-btn-secondary" onclick="viewBookingDetails(${booking.id})">View Details</button>
        ` : ''}
      </div>
    `;
    
    listEl.appendChild(card);
  });
}

// Confirm booking
async function confirmBooking(bookingId) {
  if (!confirm('Are you sure you want to confirm this booking?')) {
    return;
  }
  
  try {
    const formData = new FormData();
    formData.append('action', 'confirm_booking');
    formData.append('booking_id', bookingId);
    
    const response = await fetch('api.php', {
      method: 'POST',
      body: formData
    });
    
    if (!response.ok) {
      throw new Error('Failed to confirm booking');
    }
    
    const result = await response.json();
    
    if (result.success) {
      showStatus('Booking confirmed successfully!');
      loadBookingsData();
      updateDashboardStats();
    } else {
      throw new Error(result.error || 'Failed to confirm booking');
    }
  } catch (error) {
    console.error('Confirm booking error:', error);
    showStatus('Failed to confirm booking: ' + error.message, 'error');
  }
}

// Cancel booking
async function cancelBooking(bookingId) {
  const reason = prompt('Please enter a reason for cancellation (optional):');
  if (reason === null) {
    return; // User cancelled
  }
  
  if (!confirm('Are you sure you want to cancel this booking?')) {
    return;
  }
  
  try {
    const formData = new FormData();
    formData.append('action', 'cancel_booking');
    formData.append('booking_id', bookingId);
    if (reason) {
      formData.append('reason', reason);
    }
    
    const response = await fetch('api.php', {
      method: 'POST',
      body: formData
    });
    
    if (!response.ok) {
      throw new Error('Failed to cancel booking');
    }
    
    const result = await response.json();
    
    if (result.success) {
      showStatus('Booking cancelled successfully!');
      loadBookingsData();
      updateDashboardStats();
    } else {
      throw new Error(result.error || 'Failed to cancel booking');
    }
  } catch (error) {
    console.error('Cancel booking error:', error);
    showStatus('Failed to cancel booking: ' + error.message, 'error');
  }
}

// View booking details
async function viewBookingDetails(bookingId) {
  try {
    const formData = new FormData();
    formData.append('action', 'get_booking');
    formData.append('booking_id', bookingId);
    
    const response = await fetch('api.php', {
      method: 'POST',
      body: formData
    });
    
    if (!response.ok) {
      throw new Error('Failed to load booking details');
    }
    
    const result = await response.json();
    
    if (result.success && result.data?.booking) {
      const booking = result.data.booking;
      alert(`Booking Details:\n\n` +
        `ID: ${booking.id}\n` +
        `Room: ${booking.room_name}\n` +
        `Check-in: ${formatDate(booking.checkin_date)}\n` +
        `Check-out: ${formatDate(booking.checkout_date)}\n` +
        `Guests: ${booking.guests_count}\n` +
        `Pets: ${booking.pets ? 'Yes' : 'No'}\n` +
        `Guest: ${booking.guest_name}\n` +
        `Email: ${booking.email}\n` +
        `Phone: ${booking.phone}\n` +
        `Total: ${booking.currency || 'CAD'} ${parseFloat(booking.total_amount || 0).toFixed(2)}\n` +
        `Status: ${booking.status}\n` +
        `Payment: ${booking.payment_status || 'pending'}\n` +
        `Confirmation Code: ${booking.confirmation_code || '—'}\n` +
        `Created: ${formatDateTime(booking.created_at)}\n`
      );
    }
  } catch (error) {
    console.error('View booking details error:', error);
    showStatus('Failed to load booking details: ' + error.message, 'error');
  }
}

// Initialize bookings filters
function initBookingsFilters() {
  const applyBtn = document.getElementById('bookings-filter-apply');
  const resetBtn = document.getElementById('bookings-filter-reset');
  const refreshBtn = document.getElementById('bookings-refresh');
  
  if (applyBtn) {
    applyBtn.addEventListener('click', () => {
      loadBookingsData();
    });
  }
  
  if (resetBtn) {
    resetBtn.addEventListener('click', () => {
      document.getElementById('bookings-filter-status').value = '';
      document.getElementById('bookings-filter-room').value = '';
      document.getElementById('bookings-filter-date-from').value = '';
      document.getElementById('bookings-filter-date-to').value = '';
      loadBookingsData();
    });
  }
  
  if (refreshBtn) {
    refreshBtn.addEventListener('click', () => {
      loadBookingsData();
    });
  }
}

// ==========================================
// CALENDAR MANAGEMENT
// ==========================================

// Load calendar data
async function loadCalendarData() {
  const loadingEl = document.getElementById('admin-calendar-loading');
  const gridEl = document.getElementById('admin-calendar-grid');
  
  if (loadingEl) loadingEl.style.display = 'block';
  if (gridEl) gridEl.style.display = 'none';
  
  try {
    const roomSelect = document.getElementById('calendar-room-select');
    const selectedRoom = roomSelect ? roomSelect.value : '';
    
    // Get bookings for selected room or all rooms
    const params = new URLSearchParams({ action: 'get_bookings', status: 'confirmed,pending' });
    if (selectedRoom) {
      params.append('room_name', selectedRoom);
    }
    
    const bookingsResponse = await fetch('api.php?' + params.toString(), {
      method: 'GET'
    });
    
    // Get blocked dates (manual blocking)
    const blockedParams = new URLSearchParams({ action: 'get_blocked_dates' });
    if (selectedRoom) {
      blockedParams.append('room_name', selectedRoom);
    }
    
    const blockedResponse = await fetch('api.php?' + blockedParams.toString(), {
      method: 'GET'
    });
    
    // Get Airbnb sync status (for Airbnb blocked dates)
    const airbnbParams = new URLSearchParams({ action: 'get_airbnb_sync_status' });
    if (selectedRoom) {
      airbnbParams.append('room_name', selectedRoom);
    }
    
    const airbnbResponse = await fetch('api.php?' + airbnbParams.toString(), {
      method: 'GET'
    });
    
    let bookings = [];
    if (bookingsResponse.ok) {
      const bookingsResult = await bookingsResponse.json();
      if (bookingsResult.success && bookingsResult.data?.bookings) {
        bookings = bookingsResult.data.bookings;
      }
    }
    
    let blockedDates = [];
    if (blockedResponse.ok) {
      const blockedResult = await blockedResponse.json();
      if (blockedResult.success) {
        // Получаем ручные блокировки
        if (blockedResult.data?.blocked_dates) {
          blockedDates = blockedResult.data.blocked_dates.map(b => b.blocked_date);
        }
        
        // Также получаем Airbnb заблокированные даты
        if (blockedResult.data?.airbnb_blocked_dates) {
          blockedDates = [...blockedDates, ...blockedResult.data.airbnb_blocked_dates];
        }
      }
    }
    
    // Убираем дубликаты
    blockedDates = [...new Set(blockedDates)];
    
    if (loadingEl) loadingEl.style.display = 'none';
    if (gridEl) {
      gridEl.style.display = 'grid';
      renderAdminCalendar(gridEl, bookings, blockedDates);
    }
  } catch (error) {
    console.error('Load calendar error:', error);
    if (loadingEl) loadingEl.style.display = 'none';
    showStatus('Failed to load calendar: ' + error.message, 'error');
  }
}

// Render admin calendar
function renderAdminCalendar(container, bookings, blockedDates) {
  container.innerHTML = '';
  container.className = 'admin-calendar-grid';
  
  const today = new Date();
  const months = [];
  for (let i = 0; i < 3; i++) {
    const date = new Date(today.getFullYear(), today.getMonth() + i, 1);
    months.push(date);
  }
  
  const weekdays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
  
  months.forEach((monthDate, monthIndex) => {
    const monthYear = monthDate.toLocaleString('en-US', { month: 'long', year: 'numeric' });
    const daysInMonth = new Date(monthDate.getFullYear(), monthDate.getMonth() + 1, 0).getDate();
    const firstDay = new Date(monthDate.getFullYear(), monthDate.getMonth(), 1).getDay();
    
    // Month header
    const monthHeader = document.createElement('div');
    monthHeader.className = 'admin-calendar-month';
    monthHeader.textContent = monthYear;
    container.appendChild(monthHeader);
    
    // Day headers
    weekdays.forEach(day => {
      const dayHeader = document.createElement('div');
      dayHeader.className = 'admin-calendar-day-header';
      dayHeader.textContent = day;
      container.appendChild(dayHeader);
    });
    
    // Empty cells for days before first day of month
    for (let i = 0; i < firstDay; i++) {
      const emptyCell = document.createElement('div');
      emptyCell.className = 'admin-calendar-day';
      emptyCell.style.visibility = 'hidden';
      container.appendChild(emptyCell);
    }
    
    // Days
    for (let day = 1; day <= daysInMonth; day++) {
      const date = new Date(monthDate.getFullYear(), monthDate.getMonth(), day);
      const dateString = formatDateString(date);
      
      const dayCell = document.createElement('div');
      dayCell.className = 'admin-calendar-day';
      dayCell.textContent = day;
      
      const todayDate = new Date();
      todayDate.setHours(0, 0, 0, 0);
      const cellDate = new Date(date);
      cellDate.setHours(0, 0, 0, 0);
      
      if (cellDate < todayDate) {
        dayCell.classList.add('past');
      } else if (blockedDates.includes(dateString)) {
        dayCell.classList.add('blocked');
      } else if (isDateBooked(dateString, bookings)) {
        dayCell.classList.add('booked');
        dayCell.classList.add('has-booking');
      } else {
        dayCell.classList.add('available');
      }
      
      container.appendChild(dayCell);
    }
  });
}

// Block date
async function blockDate() {
  const roomSelect = document.getElementById('block-room-select');
  const dateInput = document.getElementById('block-date');
  const reasonInput = document.getElementById('block-reason');
  
  if (!roomSelect || !dateInput) {
    showStatus('Please fill in all required fields', 'error');
    return;
  }
  
  const roomName = roomSelect.value;
  const date = dateInput.value;
  const reason = reasonInput ? reasonInput.value : '';
  
  if (!roomName || !date) {
    showStatus('Please select room and date', 'error');
    return;
  }
  
  try {
    const formData = new FormData();
    formData.append('action', 'block_dates');
    formData.append('room_name', roomName);
    formData.append('dates[]', date);
    if (reason) {
      formData.append('reason', reason);
    }
    
    const response = await fetch('api.php', {
      method: 'POST',
      body: formData
    });
    
    if (!response.ok) {
      throw new Error('Failed to block date');
    }
    
    const result = await response.json();
    
    if (result.success) {
      showStatus('Date blocked successfully!');
      if (dateInput) dateInput.value = '';
      if (reasonInput) reasonInput.value = '';
      loadBlockedDates();
      loadCalendarData();
    } else {
      throw new Error(result.error || 'Failed to block date');
    }
  } catch (error) {
    console.error('Block date error:', error);
    showStatus('Failed to block date: ' + error.message, 'error');
  }
}

// Unblock date
async function unblockDate(blockedDateId) {
  if (!confirm('Are you sure you want to unblock this date?')) {
    return;
  }
  
  try {
    const formData = new FormData();
    formData.append('action', 'unblock_dates');
    formData.append('blocked_date_id', blockedDateId);
    
    const response = await fetch('api.php', {
      method: 'POST',
      body: formData
    });
    
    if (!response.ok) {
      throw new Error('Failed to unblock date');
    }
    
    const result = await response.json();
    
    if (result.success) {
      showStatus('Date unblocked successfully!');
      loadBlockedDates();
      loadCalendarData();
    } else {
      throw new Error(result.error || 'Failed to unblock date');
    }
  } catch (error) {
    console.error('Unblock date error:', error);
    showStatus('Failed to unblock date: ' + error.message, 'error');
  }
}

// Load blocked dates
async function loadBlockedDates() {
  const listEl = document.getElementById('blocked-dates-list');
  if (!listEl) return;
  
  try {
    const params = new URLSearchParams({ action: 'get_blocked_dates' });
    const response = await fetch('api.php?' + params.toString(), {
      method: 'GET'
    });
    
    if (!response.ok) {
      throw new Error('Failed to load blocked dates');
    }
    
    const result = await response.json();
    
    listEl.innerHTML = '';
    
    if (result.success && result.data?.blocked_dates && result.data.blocked_dates.length > 0) {
      result.data.blocked_dates.forEach(blocked => {
        const item = document.createElement('div');
        item.className = 'blocked-date-item';
        item.innerHTML = `
          <div>
            <strong>${escapeHtml(blocked.room_name || '—')}</strong>
            <div style="font-size: 14px; color: #718096; margin-top: 4px;">
              ${formatDate(blocked.blocked_date)}
              ${blocked.reason ? ` — ${escapeHtml(blocked.reason)}` : ''}
            </div>
          </div>
          <button class="admin-btn admin-btn-danger" onclick="unblockDate(${blocked.id})">Unblock</button>
        `;
        listEl.appendChild(item);
      });
    } else {
      listEl.innerHTML = '<p style="color: #718096; text-align: center; padding: 20px;">No blocked dates</p>';
    }
  } catch (error) {
    console.error('Load blocked dates error:', error);
    listEl.innerHTML = '<p style="color: #e53e3e;">Failed to load blocked dates</p>';
  }
}

// Initialize calendar blocking
function initCalendarBlocking() {
  const blockBtn = document.getElementById('block-date-btn');
  const refreshBtn = document.getElementById('calendar-refresh');
  const roomSelect = document.getElementById('calendar-room-select');
  const syncBtn = document.getElementById('airbnb-sync-btn');
  
  if (blockBtn) {
    blockBtn.addEventListener('click', blockDate);
  }
  
  if (refreshBtn) {
    refreshBtn.addEventListener('click', () => {
      loadCalendarData();
    });
  }
  
  if (roomSelect) {
    roomSelect.addEventListener('change', () => {
      loadCalendarData();
    });
  }
  
  if (syncBtn) {
    syncBtn.addEventListener('click', syncAirbnbCalendar);
  }
  
  // Загружаем статус синхронизации при открытии раздела
  loadAirbnbSyncStatus();
}

// Синхронизация календаря Airbnb
async function syncAirbnbCalendar() {
  const syncBtn = document.getElementById('airbnb-sync-btn');
  const statusEl = document.getElementById('airbnb-sync-status');
  const statusTextEl = document.getElementById('airbnb-sync-status-text');
  
  if (!syncBtn || !statusEl || !statusTextEl) {
    return;
  }
  
  // Показываем состояние загрузки
  syncBtn.disabled = true;
  syncBtn.textContent = 'Syncing...';
  statusEl.style.display = 'block';
  statusTextEl.textContent = 'Syncing with Airbnb calendar...';
  statusTextEl.style.color = '#4299e1';
  
  try {
    const formData = new FormData();
    formData.append('action', 'sync_airbnb');
    
    const response = await fetch('api.php', {
      method: 'POST',
      body: formData
    });
    
    if (!response.ok) {
      throw new Error('Failed to sync Airbnb calendar');
    }
    
    const result = await response.json();
    
    if (result.success) {
      statusTextEl.textContent = `Sync completed successfully! ${result.data?.synced_rooms?.length || 0} room(s) synced.`;
      statusTextEl.style.color = '#2f855a';
      
      // Если были ошибки, показываем их
      if (result.data?.errors && result.data.errors.length > 0) {
        const errors = result.data.errors.map(e => `${e.room}: ${e.error}`).join(', ');
        statusTextEl.textContent += ` Errors: ${errors}`;
        statusTextEl.style.color = '#e53e3e';
      }
      
      // Обновляем календарь после синхронизации
      setTimeout(() => {
        loadCalendarData();
        loadAirbnbSyncStatus();
      }, 1000);
    } else {
      throw new Error(result.error || 'Sync failed');
    }
  } catch (error) {
    console.error('Sync Airbnb error:', error);
    statusTextEl.textContent = 'Failed to sync Airbnb calendar: ' + error.message;
    statusTextEl.style.color = '#e53e3e';
  } finally {
    syncBtn.disabled = false;
    syncBtn.textContent = 'Sync with Airbnb';
  }
}

// Загрузка статуса синхронизации Airbnb
async function loadAirbnbSyncStatus() {
  const statusEl = document.getElementById('airbnb-sync-status');
  const statusTextEl = document.getElementById('airbnb-sync-status-text');
  
  if (!statusEl || !statusTextEl) {
    return;
  }
  
  try {
    const params = new URLSearchParams({ action: 'get_airbnb_sync_status' });
    const response = await fetch('api.php?' + params.toString(), {
      method: 'GET'
    });
    
    if (response.ok) {
      const result = await response.json();
      
      if (result.success && result.data?.sync_status && result.data.sync_status.length > 0) {
        const status = result.data.sync_status;
        const statusText = status.map(s => {
          const lastSync = s.last_synced ? new Date(s.last_synced).toLocaleString() : 'Never';
          return `${s.room_name}: ${s.blocked_count} blocked dates, last synced: ${lastSync}`;
        }).join('<br>');
        
        statusTextEl.innerHTML = `<strong>Last Sync Status:</strong><br>${statusText}`;
        statusTextEl.style.color = '#4a5568';
        statusEl.style.display = 'block';
      } else {
        statusTextEl.textContent = 'No sync status available. Click "Sync with Airbnb" to start syncing.';
        statusTextEl.style.color = '#718096';
        statusEl.style.display = 'block';
      }
    }
  } catch (error) {
    console.error('Load sync status error:', error);
    // Не показываем ошибку, просто скрываем статус
    statusEl.style.display = 'none';
  }
}

// ==========================================
// DASHBOARD STATS
// ==========================================

// Update dashboard statistics
async function updateDashboardStats() {
  try {
    const params = new URLSearchParams({ action: 'get_bookings' });
    const response = await fetch('api.php?' + params.toString(), {
      method: 'GET'
    });
    
    if (response.ok) {
      const result = await response.json();
      if (result.success && result.data?.bookings) {
        const bookings = result.data.bookings;
        const pendingCount = bookings.filter(b => b.status === 'pending').length;
        const totalCount = bookings.length;
        
        const pendingEl = document.getElementById('pending-bookings');
        const totalEl = document.getElementById('total-bookings');
        
        if (pendingEl) pendingEl.textContent = pendingCount;
        if (totalEl) totalEl.textContent = totalCount;
      }
    }
  } catch (error) {
    console.error('Update dashboard stats error:', error);
  }
}

// ==========================================
// HELPER FUNCTIONS
// ==========================================

function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

function formatDate(dateString) {
  if (!dateString) return '—';
  try {
    const date = new Date(dateString + 'T00:00:00');
    const months = ['January', 'February', 'March', 'April', 'May', 'June', 
                   'July', 'August', 'September', 'October', 'November', 'December'];
    return `${months[date.getMonth()]} ${String(date.getDate()).padStart(2, '0')}, ${date.getFullYear()}`;
  } catch (e) {
    return dateString;
  }
}

function formatDateTime(dateString) {
  if (!dateString) return '—';
  try {
    const date = new Date(dateString);
    return date.toLocaleString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  } catch (e) {
    return dateString;
  }
}

function formatDateString(date) {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

function isDateBooked(dateString, bookings) {
  const checkDate = new Date(dateString + 'T00:00:00');
  
  return bookings.some(booking => {
    const checkin = new Date(booking.checkin_date + 'T00:00:00');
    const checkout = new Date(booking.checkout_date + 'T00:00:00');
    return checkDate >= checkin && checkDate < checkout;
  });
}

// Export functions for global access
window.showSection = showSection;
window.editRoom = (index) => { /* TODO: Implement edit room */ };
window.deleteRoom = (index) => { /* TODO: Implement delete room */ };
window.editMassage = (index) => { /* TODO: Implement edit massage */ };
window.deleteMassage = (index) => { /* TODO: Implement delete massage */ };
window.editYoga = (index) => { /* TODO: Implement edit yoga */ };
window.deleteYoga = (index) => { /* TODO: Implement delete yoga */ };
window.deleteImage = (index) => { /* TODO: Implement delete image */ };
window.confirmBooking = confirmBooking;
window.cancelBooking = cancelBooking;
window.viewBookingDetails = viewBookingDetails;
window.unblockDate = unblockDate;





