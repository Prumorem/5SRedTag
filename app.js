// State
window.APP_VERSION = '2.0.0';
let currentFactory = localStorage.getItem('selectedFactory') || '1st Floor';
let tags = [];
let mapImage = null;
let mapInstance = null;
let imageOverlay = null;
let filterStatus = 'All';
let filterZone = 'All';

const zonesByFactory = {
    "1st Floor": [
        "QC incoming",
        "SA OQC",
        "3M08 OQC",
        "3M08 + SA9",
        "SA4-SA8",
        "SA1 - SA3",
        "Molding+Tapping",
        "Mini hub SA",
        "Cleaning box & Tray keeping",
        "Offline",
        "SA Maintenance",
        "SA Maintenance Cleaning Nozzle Area",
        "SA Maintenance Mold Area",
        "TX Recieving area (West side)",
        "RX Recieving area (North side)",
        "Transfer area ,Forklift charger ,Jig/Tools cabinet",
        "Change boxes ,FG and Storage area (out of shelf) P-Q-R",
        "FG and Storage J-K-L-M-N-O",
        "Storage shelf E-F-G-H",
        "Storage shelf A-B-C-D-I",
        "Refrigerator",
        "Chemical room",
        "Outside lockers,reception room,in front of the reception room,restroom and the stair on the west side,Walkway",
        "Staff zone 1st floor,Meeting room 1,Storage room under the stairs,Restroom on the east side,Relax room 1st floor",
        "Spare part room",
        "Workshop room",
        "Set up room"
    ],
    "2nd Floor": [
        "TX-OQC",
        "Relax zone font 2nd floor",
        "Relax zone near staff room 2nd floor",
        "Meeting room (All)",
        "Toilet 2nd floor",
        "Training&CFT Area",
        "Staff Area",
        "SA517-SA-X27",
        "SA3D11CT-1, SA3D11C-2",
        "Mini hub TX",
        "Nidec&Marelli",
        "Tokairika&Marelli",
        "Hella&NPP",
        "Global-B",
        "Analysis Area (Shelf+Cart Zone)",
        "Analysis Area (Inspection area)",
        "Analysis Area (Electrical analysis)",
        "JCS Area",
        "Shutter room",
        "Storage room",
        "Electrical room",
        "TX Maintenance"
    ]
};
let isMovingTag = false;
let movingTagId = null;

// Icons
const getStatusIcon = (status) => {
    let color = '#ef4444'; // red-500
    if (status === 'In Progress') color = '#eab308'; // yellow-500
    if (status === 'Closed') color = '#22c55e'; // green-500
    if (status === 'Need help') color = '#f97316'; // orange-500

    return L.divIcon({
        className: 'custom-marker',
        html: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="${color}" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 100%; height: 100%;"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3" fill="white"></circle></svg>`,
        iconSize: [32, 32],
        iconAnchor: [16, 32],
        popupAnchor: [0, -32],
        tooltipAnchor: [0, -32]
    });
};

function updateFilterZoneOptions() {
    const filterZoneSelect = document.getElementById('filterZone');
    if (!filterZoneSelect) return;

    const zones = zonesByFactory[currentFactory] || [];

    filterZoneSelect.innerHTML = '<option value="All">All Zones</option>';

    zones.forEach(zone => {
        const option = document.createElement('option');
        option.value = zone;
        option.textContent = zone;
        filterZoneSelect.appendChild(option);
    });

    filterZone = 'All';
    filterZoneSelect.value = 'All';
}
// Initialize
document.addEventListener('DOMContentLoaded', () => {
    lucide.createIcons();

    // Set initial factory dropdown value
    const factorySelect = document.getElementById('factorySelect');
    if (factorySelect) {
        factorySelect.value = currentFactory;

        // Listen for factory changes
        factorySelect.addEventListener('change', (e) => {
            currentFactory = e.target.value;
            localStorage.setItem('selectedFactory', currentFactory);

            updateFilterZoneOptions(); // เพิ่มบรรทัดนี้
            loadData();
        });
    }

    updateFilterZoneOptions();

    loadData();
    initMap();
    bindEvents();
});

async function loadData() {
    try {
        const response = await fetch(`api.php?action=load&factory=${currentFactory}`);
        const data = await response.json();

        tags = data.tags || [];
        mapImage = data.map || null;

        renderApp();
    } catch (error) {
        console.error('Error loading data:', error);
        alert('Failed to load data from server.');
    }
}

async function saveData(tagData) {
    try {
        const response = await fetch('api.php?action=save', {
            method: 'POST',
            body: JSON.stringify(tagData)
        });

        let result;
        try {
            result = await response.json();
        } catch (e) {
            console.error("Invalid JSON response", e);
            throw new Error("Server returned invalid response. Check PHP logs.");
        }

        if (result.success) {
            loadData(); // Reload to ensure sync
        } else {
            console.error('Save failed:', result);
            alert('Failed to save tag: ' + (result.error || 'Unknown error'));
        }
    } catch (error) {
        console.error('Error saving:', error);
        alert('Error saving tag: ' + error.message);
    }
}

async function saveMap(base64Data) {
    try {
        await fetch('api.php?action=save_map', {
            method: 'POST',
            body: JSON.stringify({ map: base64Data })
        });
    } catch (error) {
        console.error('Error saving map:', error);
    }
}

async function deleteTag(id) {
    if (window.openDeleteModal) {
        window.openDeleteModal(id);
    } else {
        console.error("Delete modal not initialized");
    }
}

async function performDelete(id) {
    try {
        await fetch('api.php?action=delete', {
            method: 'POST',
            body: JSON.stringify({ id })
        });
        loadData();
    } catch (error) {
        console.error('Error deleting:', error);
    }
}

const MAX_FILE_SIZE = 2 * 1024 * 1024; // 2MB trigger
const MAX_WIDTH = 800; // Max width for resizing
const JPEG_QUALITY = 0.5; // Compression quality

async function compressImage(file) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.readAsDataURL(file);
        reader.onload = (event) => {
            const img = new Image();
            img.src = event.target.result;
            img.onload = () => {
                const canvas = document.createElement('canvas');
                let width = img.width;
                let height = img.height;

                // Caculate new dimensions
                if (width > MAX_WIDTH) {
                    height = Math.round((height * MAX_WIDTH) / width);
                    width = MAX_WIDTH;
                }

                canvas.width = width;
                canvas.height = height;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, width, height);

                canvas.toBlob((blob) => {
                    if (blob) {
                        // Return new file, preserve original name but force jpg
                        const newFile = new File([blob], file.name.replace(/\.[^/.]+$/, "") + ".jpg", {
                            type: "image/jpeg",
                            lastModified: Date.now(),
                        });
                        resolve(newFile);
                    } else {
                        reject(new Error('Canvas to Blob failed'));
                    }
                }, 'image/jpeg', JPEG_QUALITY);
            };
            img.onerror = (err) => reject(err);
        };
        reader.onerror = (err) => reject(err);
    });
}


async function uploadImage(file) {
    // Check constraints and compress if needed
    // Check constraints and compress if needed
    if (file.size > MAX_FILE_SIZE) {
        // Simple Toast or Log
        const toast = document.createElement('div');
        toast.innerText = `Compressing large image...`;
        toast.className = 'fixed bottom-4 right-4 bg-blue-600 text-white px-4 py-2 rounded shadow-lg z-[10000]';
        document.body.appendChild(toast);

        try {
            const compressed = await compressImage(file);
            console.log(`Original: ${file.size}, Compressed: ${compressed.size}`);
            if (compressed.size < file.size) {
                file = compressed;
            }
            // Remove toast
            setTimeout(() => toast.remove(), 2000);
        } catch (e) {
            console.warn("Compression failed, trying original", e);
            toast.innerText = "Compression failed. Uploading original...";
            toast.className = 'fixed bottom-4 right-4 bg-red-600 text-white px-4 py-2 rounded shadow-lg z-[10000]';
            setTimeout(() => toast.remove(), 3000);
        }
    }

    const formData = new FormData();
    formData.append('file', file);

    try {
        const response = await fetch('api.php?action=upload', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        if (result.path) {
            return result.path;
        } else {
            throw new Error(result.error || 'Upload failed');
        }
    } catch (error) {
        // Better error message
        let msg = error.message;
        if (msg.includes('JSON')) msg = 'Server returned invalid response (file might be too large)';
        alert('Image upload failed: ' + msg);
        return null;
    }
}

function initMap() {
    mapInstance = L.map('map', {
        crs: L.CRS.Simple,
        minZoom: -2,
        zoom: 1,
        zoomControl: false  // Disable default (topleft) zoom control
    });
    window.mapInstance = mapInstance; // Expose for sidebar toggle

    // Add zoom control at top-right to avoid overlap with sidebar toggle button
    L.control.zoom({ position: 'topright' }).addTo(mapInstance);

    mapInstance.on('click', (e) => {
        if (!mapImage) return;

        if (isMovingTag) {
            handleMoveTagClick(e.latlng);
        } else {
            handleAddTagClick(e.latlng);
        }
    });
}

function bindEvents() {
    // State for Password Modal
    let isDeleteAction = false;
    let pendingDeleteId = null;

    // Map Upload Logic (2-Step Modal)
    const modal = document.getElementById('passwordModal');
    const pwStep = document.getElementById('pwStep');
    const uploadStep = document.getElementById('uploadStep');
    const pwInput = document.getElementById('passwordInput');
    const fileInput = document.getElementById('modalMapUpload');
    const modalTitle = document.querySelector('#passwordModal h3');

    // 1. Open Modal (Password Step) - Upload Flow
    document.getElementById('mapUploadBtn').addEventListener('click', () => {
        isDeleteAction = false;
        pendingDeleteId = null;

        pwInput.value = '';
        fileInput.value = '';
        if (modalTitle) modalTitle.innerText = "Admin Access (Upload)";

        pwStep.classList.remove('hidden');
        uploadStep.classList.add('hidden');
        modal.classList.remove('hidden');
    });

    // User Guide Logic
    const userGuideModal = document.getElementById('userGuideModal');
    if (userGuideModal) {
        document.getElementById('userGuideBtn').addEventListener('click', () => {
            userGuideModal.classList.remove('hidden');
        });
        document.getElementById('closeUserGuideBtn').addEventListener('click', () => {
            userGuideModal.classList.add('hidden');
        });
        document.getElementById('closeUserGuideBottomBtn').addEventListener('click', () => {
            userGuideModal.classList.add('hidden');
        });
        userGuideModal.addEventListener('click', (e) => {
            if (e.target === userGuideModal) {
                userGuideModal.classList.add('hidden');
            }
        });
    }

    // Helper to close and reset
    const closeAll = () => {
        modal.classList.add('hidden');
        isDeleteAction = false;
        pendingDeleteId = null;
        pwInput.value = '';
    };

    // Cancel Buttons
    document.getElementById('cancelPwBtn').addEventListener('click', closeAll);
    document.getElementById('cancelUploadBtn').addEventListener('click', closeAll);

    // 2. Verify Password -> Action
    document.getElementById('verifyPwBtn').addEventListener('click', async () => {
        const password = pwInput.value;
        if (await checkPassword(password)) {
            if (isDeleteAction) {
                // DELETE FLOW
                if (pendingDeleteId) {
                    await performDelete(pendingDeleteId);
                }
                closeAll();
            } else {
                // UPLOAD FLOW
                pwStep.classList.add('hidden');
                uploadStep.classList.remove('hidden');
            }
        } else {
            alert('Incorrect password.');
        }
    });

    // 3. Final Upload
    document.getElementById('finalUploadBtn').addEventListener('click', () => {
        if (!fileInput.files[0]) {
            alert('Please select a file.');
            return;
        }
        handleMapUpload();
        closeAll();
    });

    // Expose deleteTag modal opener
    window.openDeleteModal = (id) => {
        isDeleteAction = true;
        pendingDeleteId = id;

        pwInput.value = '';
        if (modalTitle) modalTitle.innerText = "Admin Access (Delete)";

        pwStep.classList.remove('hidden');
        uploadStep.classList.add('hidden');
        modal.classList.remove('hidden');
    };

    // document.getElementById('mapUpload').addEventListener('change', handleMapUpload); // OLD logic removed
    document.getElementById('moveBtn').addEventListener('click', handleMoveBtnClick);
    document.getElementById('exportBtn').addEventListener('click', () => {
        // Direct download from server
        window.open('data/tags.csv', '_blank');
    });
    document.getElementById('filterSelect').addEventListener('change', (e) => {
        filterStatus = e.target.value;
        renderApp();
    });
    document.getElementById('filterZone').addEventListener('change', (e) => {
        filterZone = e.target.value;
        renderApp();
    });

    // Status Change in Form
    document.getElementById('status').addEventListener('change', (e) => {
        const isClosed = e.target.value === 'Closed';
        const solutionRequired = document.getElementById('solutionRequired');
        const photoAfterRequired = document.getElementById('photoAfterRequired');

        if (isClosed) {
            solutionRequired.classList.remove('hidden');
            photoAfterRequired.classList.remove('hidden');
        } else {
            solutionRequired.classList.add('hidden');
            photoAfterRequired.classList.add('hidden');
        }
    });

    const tagForm = document.getElementById('tagForm');
    if (tagForm) tagForm.addEventListener('submit', handleFormSubmit);

    const cancelBtn = document.getElementById('cancelBtn');
    if (cancelBtn) cancelBtn.addEventListener('click', closeModal);

    const closeModalBtn = document.getElementById('closeModalBtn');
    if (closeModalBtn) closeModalBtn.addEventListener('click', closeModal);

    const tagImageInput = document.getElementById('tagImageInput');
    if (tagImageInput) tagImageInput.addEventListener('change', handleTagImageUpload);

    const tagImageInputAfter = document.getElementById('tagImageInputAfter');
    if (tagImageInputAfter) tagImageInputAfter.addEventListener('change', handleTagImageAfterUpload);
}

function renderApp() {
    renderMap();
    renderList();
    renderStats();
}

function renderMap() {
    mapInstance.eachLayer((layer) => {
        mapInstance.removeLayer(layer);
    });

    if (mapImage) {
        document.getElementById('emptyState').classList.add('hidden');

        const img = new Image();
        img.src = mapImage;
        img.onload = () => {
            const h = img.naturalHeight;
            const w = img.naturalWidth;
            const bounds = [[0, 0], [h, w]];

            imageOverlay = L.imageOverlay(mapImage, bounds).addTo(mapInstance);

            if (img.src !== mapInstance._lastImgSrc) {
                mapInstance.fitBounds(bounds);
                mapInstance._lastImgSrc = img.src;
            }
        };
    } else {
        document.getElementById('emptyState').classList.remove('hidden');
    }

    const filteredTags = getFilteredTags();
    filteredTags.forEach(tag => {
        const marker = L.marker([tag.x, tag.y], {
            icon: getStatusIcon(tag.status)
        }).addTo(mapInstance);

        marker.bindTooltip(`
            <div class="text-center">
                <h3 class="font-bold text-sm">${tag.productionLine}</h3>
                <p class="text-xs font-medium">${tag.status}</p>
                ${tag.image ? `<img src="${tag.image}" width="64" height="64" loading="lazy" decoding="async" class="w-16 h-16 object-cover mt-1 mx-auto rounded" alt="Tag photo">` : ''}
            </div>
        `, {
            direction: 'top',
            offset: [0, -32],
            opacity: 1
        });

        marker.on('click', (e) => {
            L.DomEvent.stopPropagation(e);
            openModal(tag);
        });
    });
}

function renderList() {
    const listContainer = document.getElementById('tagList');
    listContainer.innerHTML = '';

    const filteredTags = getFilteredTags();

    if (filteredTags.length === 0) {
        listContainer.innerHTML = `
            <div class="p-8 text-center text-gray-500">
                <i data-lucide="map-pin" class="w-12 h-12 mx-auto mb-2 opacity-20"></i>
                <p>No Red Tags yet.</p>
                <p class="text-sm">Click on the map to add one.</p>
            </div>
        `;
        lucide.createIcons();
        return;
    }

    filteredTags.forEach(tag => {
        const item = document.createElement('div');
        item.className = 'bg-white p-4 rounded-lg shadow border border-gray-100 hover:border-red-200 transition-colors cursor-pointer';
        item.onclick = () => openModal(tag);

        const statusBgColor = tag.status === 'Closed' ? '#22c55e' : (tag.status === 'In Progress' ? '#eab308' : (tag.status === 'Need help' ? '#f97316' : '#ef4444'));
        const badgeBgColor = tag.status === 'Closed' ? '#dcfce7' : (tag.status === 'In Progress' ? '#fef9c3' : (tag.status === 'Need help' ? '#ffedd5' : '#fee2e2'));
        const badgeTextColor = tag.status === 'Closed' ? '#15803d' : (tag.status === 'In Progress' ? '#a16207' : (tag.status === 'Need help' ? '#c2410c' : '#b91c1c'));

        item.innerHTML = `
            <div class="flex justify-between items-start mb-2">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="w-3 h-3 rounded-full shrink-0" style="background-color: ${statusBgColor};"></span>
                    <h3 class="font-semibold text-gray-800 text-sm truncate"title="${tag.zone}">Zone: ${tag.zone}</h3>
                    <span class="px-2 py-0.5 rounded-full text-[5px] border whitespace-nowrap" style="background-color: ${badgeBgColor}; color: ${badgeTextColor}; border-color: ${statusBgColor}40;">
                        ${tag.status}
                    </span>
                    ${tag.category ? `<span class="px-2 py-0.5 rounded-full text-[5px] font-normal bg-gray-100 text-gray-600 border border-gray-200 whitespace-nowrap truncate max-w-[100px]" title="${tag.category}">${tag.category.split(' (')[0]}</span>` : ''}
                </div>
                <div class="flex gap-1 shrink-0 ml-2">
                    <button class="edit-btn p-1 text-gray-400 hover:text-blue-600 rounded" title="Edit">
                        <i data-lucide="edit-2" class="w-4 h-4"></i>
                    </button>
                    <button class="delete-btn p-1 text-gray-400 hover:text-red-600 rounded" title="Delete">
                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                    </button>
                </div>
            </div>

            <div class="text-xs text-gray-500 font-medium mb-1"><span class="text-gray-700 font-semibold">${tag.productionLine}</span></div>
            
            <div class="text-sm text-gray-600 mb-2 mt-1 line-clamp-2" title="${tag.description}">${tag.description}</div>
            ${tag.solution ? `<div class="text-xs text-gray-500 mt-2 bg-gray-50 p-2 rounded"><div class="line-clamp-2"><strong class="text-gray-700">Solved:</strong> ${tag.solution}</div></div>` : ''}
        `;

        item.querySelector('.edit-btn').addEventListener('click', (e) => {
            e.stopPropagation();
            openModal(tag);
        });
        item.querySelector('.delete-btn').addEventListener('click', (e) => {
            e.stopPropagation();
            deleteTag(tag.id);
        });

        listContainer.appendChild(item);
    });

    lucide.createIcons();
}

function renderStats() {
    document.getElementById('tagCount').innerText = getFilteredTags().length;
}

function getFilteredTags() {
    const today = new Date();
    const tenDaysAgo = new Date(today);
    tenDaysAgo.setDate(tenDaysAgo.getDate() - 10);

    return tags.filter(t => {
        // Skip tags that are Closed AND older than 10 days
        if (t.status === 'Closed' && t.createdAt) {
            // Replace space with T for reliable parsing (e.g., "2023-10-15 14:30:00" -> "2023-10-15T14:30:00")
            const createdDate = new Date(t.createdAt.replace(' ', 'T'));
            if (!isNaN(createdDate.getTime()) && createdDate <= tenDaysAgo) {
                return false;
            }
        }

        const statusMatch = (filterStatus === 'All') || (t.status === filterStatus);
        const zoneMatch = (filterZone === 'All') || (String(t.zone) === filterZone);
        return statusMatch && zoneMatch;
    });
}

async function handleMapUpload() {
    const input = document.getElementById('modalMapUpload');
    const file = input.files[0];
    if (file) {
        // Show loading
        // Removed Toast as requested

        try {
            const formData = new FormData();
            formData.append('file', file);
            formData.append('factory', currentFactory);

            const response = await fetch('api.php?action=upload_floor_plan', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.success) {
                // Determine path or just reload to get it from load()
                // Reloading is safest to get cache busted URL
                loadData();
            } else {
                alert('Floor plan upload failed: ' + (result.error || 'Unknown error'));
            }
        } catch (err) {
            console.error(err);
            alert('Error uploading floor plan');
        } finally {
            // Restore UI (or just reload logic above handles it)
            // If we didn't reload (error case), we might want to restore
            // But actually we need to find the element again since innerHTML nuked it.
            // Simplest is to just reload page or re-render if error?
            // User can just refresh if stuck.
            // Let's rely on loadData() re-rendering.
        }
    }
}

async function handleTagImageUpload(e) {
    const file = e.target.files[0];
    if (file) {
        // Show loading state
        const placeholder = document.getElementById('uploadPlaceholder');
        const preview = document.getElementById('imagePreview');
        const originalText = placeholder.innerHTML;
        placeholder.innerHTML = '<p class="text-sm text-gray-500">Uploading...</p>';

        try {
            const path = await uploadImage(file);
            if (path) {
                preview.src = path;
                preview.classList.remove('hidden');
                placeholder.classList.add('hidden');
            } else {
                // uploadImage already alerted
                throw new Error('Upload failed (handled)');
            }
        } catch (err) {
            console.error(err);
            if (err.message !== 'Upload failed (handled)') {
                alert('Failed to upload "Before" image. Please check file size and permissions.');
            }
        } finally {
            placeholder.innerHTML = originalText;
            e.target.value = '';
        }
    }
}

async function handleTagImageAfterUpload(e) {
    const file = e.target.files[0];
    if (file) {
        // Show loading state
        const placeholder = document.getElementById('uploadPlaceholderAfter');
        const preview = document.getElementById('imagePreviewAfter');
        const originalText = placeholder.innerHTML;
        placeholder.innerHTML = '<p class="text-sm text-gray-500">Uploading...</p>';

        try {
            const path = await uploadImage(file);
            if (path) {
                preview.src = path;
                preview.classList.remove('hidden');
                placeholder.classList.add('hidden');
            } else {
                // uploadImage already alerted
                throw new Error('Upload failed (handled)');
            }
        } catch (err) {
            console.error(err);
            if (err.message !== 'Upload failed (handled)') {
                alert('Failed to upload "After" image. Please check file size and permissions.');
            }
        } finally {
            placeholder.innerHTML = originalText;
            e.target.value = '';
        }
    }
}


function handleAddTagClick(latlng) {
    // Get last selected zone from localStorage
    const lastZone = localStorage.getItem('lastSelectedZone') || '';

    openModal({
        id: Date.now(),
        x: latlng.lat,
        y: latlng.lng,
        productionLine: '',
        description: '',
        solution: '',
        status: 'Open',
        image: null,
        imageAfter: null,
        zone: '',
        pic: '',
        category: '',
        factory: currentFactory
    });
}

function openModal(tagData = null) {
    const modal = document.getElementById('tagModal');

    if (tagData) {
        // Show/Hide Move Button: only if editing existing (id exists in list)
        // Actually tagData always has ID if opened ? No, new tag has ID generated in handleAddTagClick
        // We can check if it exists in tags array
        const exists = tags.some(t => t.id === tagData.id);
        const moveBtn = document.getElementById('moveBtn');
        if (exists) {
            moveBtn.classList.remove('hidden');
        } else {
            moveBtn.classList.add('hidden');
        }

        document.getElementById('tagId').value = tagData.id || '';
        document.getElementById('tagX').value = tagData.x || 0;
        document.getElementById('tagY').value = tagData.y || 0;
        document.getElementById('productionLine').value = tagData.productionLine || '';
        document.getElementById('description').value = tagData.description || '';
        document.getElementById('solution').value = tagData.solution || '';
        document.getElementById('status').value = tagData.status || 'Open';
        document.getElementById('category').value = tagData.category || '';
        document.getElementById('pic').value = tagData.pic || '';

        // Zone logic: use tag data, or last selected if new (handled in handleAddTagClick), or empty
        document.getElementById('zone').value = tagData.zone || '';

        // Update required indicators
        const isClosed = (tagData.status || 'Open') === 'Closed';
        const solutionRequired = document.getElementById('solutionRequired');
        const photoAfterRequired = document.getElementById('photoAfterRequired');
        if (isClosed) {
            solutionRequired.classList.remove('hidden');
            photoAfterRequired.classList.remove('hidden');
        } else {
            solutionRequired.classList.add('hidden');
            photoAfterRequired.classList.add('hidden');
        }

        const preview = document.getElementById('imagePreview');
        const placeholder = document.getElementById('uploadPlaceholder');

        if (tagData.image) {
            preview.src = tagData.image;
            preview.classList.remove('hidden');
            placeholder.classList.add('hidden');
        } else {
            preview.src = '';
            preview.classList.add('hidden');
            placeholder.classList.remove('hidden');
        }

        const previewAfter = document.getElementById('imagePreviewAfter');
        const placeholderAfter = document.getElementById('uploadPlaceholderAfter');

        if (tagData.imageAfter) {
            previewAfter.src = tagData.imageAfter;
            previewAfter.classList.remove('hidden');
            placeholderAfter.classList.add('hidden');
        } else {
            previewAfter.src = '';
            previewAfter.classList.add('hidden');
            placeholderAfter.classList.remove('hidden');
        }
    }

    modal.classList.remove('hidden');
}

function closeModal() {
    document.getElementById('tagModal').classList.add('hidden');
}

function handleFormSubmit(e) {
    e.preventDefault();

    const id = parseInt(document.getElementById('tagId').value) || Date.now();
    const x = parseFloat(document.getElementById('tagX').value);
    const y = parseFloat(document.getElementById('tagY').value);
    const productionLine = document.getElementById('productionLine').value;
    const description = document.getElementById('description').value;
    const solution = document.getElementById('solution').value;
    const status = document.getElementById('status').value;
    const category = document.getElementById('category').value;
    const pic = document.getElementById('pic').value;
    const zone = document.getElementById('zone').value;

    // Get image path from preview src (since upload happens on change)
    const preview = document.getElementById('imagePreview');
    const image = (!preview.classList.contains('hidden')) ? preview.src : null; // This might be full URL

    const previewAfter = document.getElementById('imagePreviewAfter');
    const imageAfter = (!previewAfter.classList.contains('hidden')) ? previewAfter.src : null;

    // --- Validation Logic ---
    if (!zone) {
        alert('Zone is required.');
        return;
    }
    if (!image) {
        alert('Photo (Before) is required.');
        return;
    }
    if (!description.trim()) {
        alert('Description / Problem is required.');
        return;
    }
    if (!status) {
        alert('Status is required.');
        return;
    }
    if (!category) {
        alert('Category is required.');
        return;
    }


    // Check for required solution and photo after if Closed
    if (status === 'Closed') {
        if (!solution.trim()) {
            alert('Solution / Countermeasure is required when status is Closed.');
            return;
        }
        if (!imageAfter) {
            alert('Photo (After) is required when status is Closed.');
            return;
        }
    }
    // ------------------------

    // Save last selected zone to localStorage

    const newTag = {
        id,
        x,
        y,
        productionLine,
        description,
        solution,
        status,
        image,
        imageAfter,
        zone,
        pic,
        category,
        factory: currentFactory
    };

    saveData(newTag);
    closeModal();
}

function handleMoveBtnClick() {
    movingTagId = document.getElementById('tagId').value; // Keep as string or whatever is in value
    if (!movingTagId) return;

    isMovingTag = true;
    closeModal();

    const toast = document.createElement('div');
    toast.id = 'moveToast';
    toast.className = 'fixed top-4 left-1/2 transform -translate-x-1/2 bg-blue-600 text-white px-6 py-3 rounded-full shadow-lg z-[10000] font-bold animate-pulse';
    toast.innerText = 'Click on the map to set new position';
    document.body.appendChild(toast);
}

function handleMoveTagClick(latlng) {
    const toast = document.getElementById('moveToast');
    if (toast) toast.remove();

    // Use loose equality (==) to match string "123" with number 123
    const tagToMove = tags.find(t => t.id == movingTagId);
    if (tagToMove) {
        tagToMove.x = latlng.lat;
        tagToMove.y = latlng.lng;

        saveData(tagToMove);

        isMovingTag = false;
        movingTagId = null;
    } else {
        alert('Error: Could not find tag to move.');
        isMovingTag = false;
        movingTagId = null;
    }
}

async function checkPassword(input) {
    try {
        const response = await fetch('api.php?action=check_password', {
            method: 'POST',
            body: JSON.stringify({ password: input })
        });
        const result = await response.json();
        return result.success;
    } catch (e) {
        console.error(e);
        return false;
    }
}
