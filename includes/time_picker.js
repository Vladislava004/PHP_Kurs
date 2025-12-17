/**
 * Кастомный селектор времени в 24-часовом формате
 */

function initTimePicker(inputId) {
    const input = document.getElementById(inputId);
    if (!input) return;
    
    // Создаем контейнер для кастомного селектора
    const container = document.createElement('div');
    container.className = 'time-picker-container';
    container.style.display = 'none';
    container.style.position = 'absolute';
    container.style.background = 'white';
    container.style.border = '1px solid #ddd';
    container.style.borderRadius = '5px';
    container.style.padding = '1rem';
    container.style.boxShadow = '0 4px 15px rgba(0,0,0,0.2)';
    container.style.zIndex = '1000';
    container.style.marginTop = '5px';
    
    // Создаем селекторы для часов и минут
    const timeSelector = document.createElement('div');
    timeSelector.style.display = 'flex';
    timeSelector.style.gap = '1rem';
    timeSelector.style.alignItems = 'center';
    
    // Часы (00-23)
    const hoursDiv = document.createElement('div');
    hoursDiv.style.display = 'flex';
    hoursDiv.style.flexDirection = 'column';
    hoursDiv.style.alignItems = 'center';
    
    const hoursLabel = document.createElement('label');
    hoursLabel.textContent = 'Часы';
    hoursLabel.style.fontSize = '0.875rem';
    hoursLabel.style.color = '#666';
    hoursLabel.style.marginBottom = '0.5rem';
    
    const hoursSelect = document.createElement('select');
    hoursSelect.style.padding = '0.5rem';
    hoursSelect.style.border = '2px solid #e0e0e0';
    hoursSelect.style.borderRadius = '5px';
    hoursSelect.style.fontSize = '1rem';
    hoursSelect.style.minWidth = '80px';
    
    for (let i = 0; i < 24; i++) {
        const option = document.createElement('option');
        option.value = String(i).padStart(2, '0');
        option.textContent = String(i).padStart(2, '0');
        hoursSelect.appendChild(option);
    }
    
    hoursDiv.appendChild(hoursLabel);
    hoursDiv.appendChild(hoursSelect);
    
    // Минуты (00-59, с шагом 1)
    const minutesDiv = document.createElement('div');
    minutesDiv.style.display = 'flex';
    minutesDiv.style.flexDirection = 'column';
    minutesDiv.style.alignItems = 'center';
    
    const minutesLabel = document.createElement('label');
    minutesLabel.textContent = 'Минуты';
    minutesLabel.style.fontSize = '0.875rem';
    minutesLabel.style.color = '#666';
    minutesLabel.style.marginBottom = '0.5rem';
    
    const minutesSelect = document.createElement('select');
    minutesSelect.style.padding = '0.5rem';
    minutesSelect.style.border = '2px solid #e0e0e0';
    minutesSelect.style.borderRadius = '5px';
    minutesSelect.style.fontSize = '1rem';
    minutesSelect.style.minWidth = '80px';
    
    for (let i = 0; i < 60; i++) {
        const option = document.createElement('option');
        option.value = String(i).padStart(2, '0');
        option.textContent = String(i).padStart(2, '0');
        minutesSelect.appendChild(option);
    }
    
    minutesDiv.appendChild(minutesLabel);
    minutesDiv.appendChild(minutesSelect);
    
    timeSelector.appendChild(hoursDiv);
    timeSelector.appendChild(minutesDiv);
    
    // Кнопки
    const buttonsDiv = document.createElement('div');
    buttonsDiv.style.display = 'flex';
    buttonsDiv.style.gap = '0.5rem';
    buttonsDiv.style.marginTop = '1rem';
    buttonsDiv.style.justifyContent = 'flex-end';
    
    const cancelBtn = document.createElement('button');
    cancelBtn.textContent = 'Отмена';
    cancelBtn.className = 'btn btn-secondary btn-sm';
    cancelBtn.type = 'button';
    cancelBtn.onclick = () => {
        container.style.display = 'none';
    };
    
    const applyBtn = document.createElement('button');
    applyBtn.textContent = 'Применить';
    applyBtn.className = 'btn btn-primary btn-sm';
    applyBtn.type = 'button';
    applyBtn.onclick = () => {
        const date = input.value.split('T')[0] || new Date().toISOString().split('T')[0];
        const hours = hoursSelect.value;
        const minutes = minutesSelect.value;
        input.value = `${date}T${hours}:${minutes}`;
        container.style.display = 'none';
    };
    
    buttonsDiv.appendChild(cancelBtn);
    buttonsDiv.appendChild(applyBtn);
    
    container.appendChild(timeSelector);
    container.appendChild(buttonsDiv);
    
    // Вставляем контейнер после input
    input.parentNode.insertBefore(container, input.nextSibling);
    
    // Кнопка для открытия селектора
    const toggleBtn = document.createElement('button');
    toggleBtn.type = 'button';
    toggleBtn.style.marginLeft = '0.5rem';
    toggleBtn.style.padding = '0.5rem';
    toggleBtn.style.border = '1px solid #ddd';
    toggleBtn.style.borderRadius = '5px';
    toggleBtn.style.background = '#f8f9fa';
    toggleBtn.style.cursor = 'pointer';
    toggleBtn.title = 'Выбрать время (24ч формат)';
    
    toggleBtn.onclick = (e) => {
        e.preventDefault();
        const currentValue = input.value;
        if (currentValue) {
            const [date, time] = currentValue.split('T');
            if (time) {
                const [h, m] = time.split(':');
                hoursSelect.value = h || '00';
                minutesSelect.value = m || '00';
            }
        } else {
            const now = new Date();
            hoursSelect.value = String(now.getHours()).padStart(2, '0');
            minutesSelect.value = String(now.getMinutes()).padStart(2, '0');
        }
        
        // Позиционируем контейнер
        const rect = input.getBoundingClientRect();
        container.style.position = 'absolute';
        container.style.top = (rect.bottom + window.scrollY) + 'px';
        container.style.left = (rect.left + window.scrollX) + 'px';
        
        container.style.display = container.style.display === 'none' ? 'block' : 'none';
    };
    
    input.parentNode.insertBefore(toggleBtn, input.nextSibling);
    
    // Закрываем при клике вне контейнера
    document.addEventListener('click', (e) => {
        if (!container.contains(e.target) && e.target !== toggleBtn && e.target !== input) {
            container.style.display = 'none';
        }
    });
}





