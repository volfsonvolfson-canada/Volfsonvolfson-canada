// Admin Content Management JavaScript

// Check if user is authenticated
function checkAdminAuth() {
  const isAuthenticated = localStorage.getItem('btb_admin_auth') === 'true';
  if (!isAuthenticated && !window.location.pathname.includes('admin-login')) {
    window.location.href = 'admin-login.html';
  }
}

// Logout function
function adminLogout() {
  localStorage.removeItem('btb_admin_auth');
  window.location.href = 'admin-login.html';
}

// Navigation between sections (tabs)
function showSection(sectionName) {
  // Hide all sections
  document.querySelectorAll('.admin-section').forEach(section => {
    section.style.display = 'none';
  });
  
  // Remove active class from all nav tabs
  document.querySelectorAll('.admin-nav-tab').forEach(tab => {
    tab.classList.remove('active');
  });
  
  // Show selected section
  const targetSection = document.getElementById(`${sectionName}-section`);
  if (targetSection) {
    targetSection.style.display = 'block';
  }
  
  // Add active class to clicked tab
  const activeTab = document.querySelector(`[data-section="${sectionName}"]`);
  if (activeTab) {
    activeTab.classList.add('active');
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
    case 'dashboard':
      // Update dashboard stats if needed
      break;
  }
}

// ==========================================
// HOMEPAGE MANAGEMENT
// ==========================================

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

// ==========================================
// FLOOR PLAN MANAGEMENT
// ==========================================

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

// ==========================================
// ROOMS MANAGEMENT
// ==========================================

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

// ==========================================
// MASSAGE MANAGEMENT
// ==========================================

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

// ==========================================
// YOGA MANAGEMENT
// ==========================================

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

// ==========================================
// CONTENT MANAGEMENT
// ==========================================

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

// ==========================================
// IMAGES MANAGEMENT
// ==========================================

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

// ==========================================
// UTILITY FUNCTIONS
// ==========================================

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
  statusDiv.style.cssText = `
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 16px 24px;
    background: ${type === 'error' ? '#e53e3e' : '#2f855a'};
    color: white;
    border-radius: 8px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    z-index: 10000;
    font-weight: 600;
  `;
  statusDiv.textContent = message;
  document.body.appendChild(statusDiv);
  
  setTimeout(() => {
    statusDiv.remove();
  }, 3000);
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

// ==========================================
// FORM HANDLERS
// ==========================================

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

// ==========================================
// INITIALIZATION
// ==========================================

// Initialize admin panel
document.addEventListener('DOMContentLoaded', () => {
  // Check authentication
  checkAdminAuth();
  
  // Navigation tabs
  document.querySelectorAll('.admin-nav-tab').forEach(tab => {
    tab.addEventListener('click', () => {
      const section = tab.getAttribute('data-section');
      if (section) {
        showSection(section);
      }
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
  
  // Show dashboard by default
  showSection('dashboard');
});

// Export functions for global access
window.showSection = showSection;
window.editRoom = (index) => { /* TODO: Implement edit room */ };
window.deleteRoom = (index) => { /* TODO: Implement delete room */ };
window.editMassage = (index) => { /* TODO: Implement edit massage */ };
window.deleteMassage = (index) => { /* TODO: Implement delete massage */ };
window.editYoga = (index) => { /* TODO: Implement edit yoga */ };
window.deleteYoga = (index) => { /* TODO: Implement delete yoga */ };
window.deleteImage = (index) => { /* TODO: Implement delete image */ };

