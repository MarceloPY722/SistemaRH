/**
 * Sistema de Autocompletado para Observaciones y Comisionamiento
 * Proporciona sugerencias dinámicas basadas en entrada del usuario
 */

class Autocomplete {
    constructor(inputElement, tipo, options = {}) {
        this.input = inputElement;
        this.tipo = tipo; // 'observaciones' o 'comisionamiento'
        this.options = {
            minLength: 2,
            delay: 300,
            maxResults: 8,
            apiUrl: 'api/sugerencias.php',
            ...options
        };
        
        this.suggestions = [];
        this.selectedIndex = -1;
        this.isVisible = false;
        this.searchTimeout = null;
        this.currentRequest = null;
        
        this.init();
    }
    
    init() {
        this.createContainer();
        this.bindEvents();
    }
    
    createContainer() {
        // Crear contenedor principal
        this.container = document.createElement('div');
        this.container.className = 'autocomplete-container';
        
        // Envolver el input
        this.input.parentNode.insertBefore(this.container, this.input);
        this.container.appendChild(this.input);
        
        // Crear lista de sugerencias
        this.suggestionsList = document.createElement('div');
        this.suggestionsList.className = 'autocomplete-suggestions';
        this.container.appendChild(this.suggestionsList);
        
        // Agregar clase al input
        this.input.classList.add('autocomplete-input');
    }
    
    bindEvents() {
        // Eventos del input
        this.input.addEventListener('input', (e) => this.handleInput(e));
        this.input.addEventListener('keydown', (e) => this.handleKeydown(e));
        this.input.addEventListener('focus', (e) => this.handleFocus(e));
        this.input.addEventListener('blur', (e) => this.handleBlur(e));
        
        // Eventos de la lista de sugerencias
        this.suggestionsList.addEventListener('mousedown', (e) => this.handleMouseDown(e));
        this.suggestionsList.addEventListener('mouseover', (e) => this.handleMouseOver(e));
        
        // Cerrar al hacer clic fuera
        document.addEventListener('click', (e) => {
            if (!this.container.contains(e.target)) {
                this.hide();
            }
        });
    }
    
    handleInput(e) {
        const query = e.target.value.trim();
        
        if (query.length < this.options.minLength) {
            this.hide();
            return;
        }
        
        // Cancelar búsqueda anterior
        if (this.searchTimeout) {
            clearTimeout(this.searchTimeout);
        }
        
        // Cancelar petición anterior
        if (this.currentRequest) {
            this.currentRequest.abort();
        }
        
        // Mostrar indicador de carga
        this.showLoading();
        
        // Realizar búsqueda con delay
        this.searchTimeout = setTimeout(() => {
            this.search(query);
        }, this.options.delay);
    }
    
    handleKeydown(e) {
        if (!this.isVisible) return;
        
        switch (e.key) {
            case 'ArrowDown':
                e.preventDefault();
                this.selectNext();
                break;
                
            case 'ArrowUp':
                e.preventDefault();
                this.selectPrevious();
                break;
                
            case 'Enter':
                e.preventDefault();
                if (this.selectedIndex >= 0) {
                    this.selectSuggestion(this.suggestions[this.selectedIndex]);
                }
                break;
                
            case 'Escape':
                e.preventDefault();
                this.hide();
                this.input.blur();
                break;
        }
    }
    
    handleFocus(e) {
        const query = e.target.value.trim();
        if (query.length >= this.options.minLength && this.suggestions.length > 0) {
            this.show();
        }
    }
    
    handleBlur(e) {
        // Delay para permitir clicks en sugerencias
        setTimeout(() => {
            this.hide();
        }, 150);
    }
    
    handleMouseDown(e) {
        e.preventDefault(); // Prevenir blur del input
        
        const suggestionElement = e.target.closest('.autocomplete-suggestion');
        if (suggestionElement) {
            const index = Array.from(this.suggestionsList.children)
                .indexOf(suggestionElement);
            
            if (index >= 0 && this.suggestions[index]) {
                this.selectSuggestion(this.suggestions[index]);
            }
        }
    }
    
    handleMouseOver(e) {
        const suggestionElement = e.target.closest('.autocomplete-suggestion');
        if (suggestionElement) {
            const index = Array.from(this.suggestionsList.children)
                .indexOf(suggestionElement);
            this.setSelectedIndex(index);
        }
    }
    
    async search(query) {
        try {
            const controller = new AbortController();
            this.currentRequest = controller;
            
            const url = `${this.options.apiUrl}?tipo=${encodeURIComponent(this.tipo)}&query=${encodeURIComponent(query)}`;
            
            const response = await fetch(url, {
                signal: controller.signal,
                headers: {
                    'Accept': 'application/json'
                }
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.error) {
                console.error('API Error:', data.error);
                this.showNoResults();
                return;
            }
            
            this.suggestions = data;
            this.selectedIndex = -1;
            
            if (this.suggestions.length > 0) {
                this.renderSuggestions();
                this.show();
            } else {
                this.showNoResults();
            }
            
        } catch (error) {
            if (error.name !== 'AbortError') {
                console.error('Search error:', error);
                this.showNoResults();
            }
        } finally {
            this.currentRequest = null;
        }
    }
    
    renderSuggestions() {
        this.suggestionsList.innerHTML = '';
        
        this.suggestions.forEach((suggestion, index) => {
            const element = document.createElement('div');
            element.className = 'autocomplete-suggestion';
            element.setAttribute('data-index', index);
            
            element.innerHTML = `
                <div class="suggestion-value">
                    ${this.escapeHtml(suggestion.valor)}
                    <span class="suggestion-type ${suggestion.tipo}">${suggestion.tipo}</span>
                </div>
                <div class="suggestion-description">
                    ${this.escapeHtml(suggestion.descripcion)}
                </div>
            `;
            
            this.suggestionsList.appendChild(element);
        });
    }
    
    showLoading() {
        this.suggestionsList.innerHTML = '<div class="autocomplete-loading">Buscando sugerencias...</div>';
        this.show();
    }
    
    showNoResults() {
        this.suggestionsList.innerHTML = '<div class="autocomplete-no-results">No se encontraron sugerencias</div>';
        this.show();
    }
    
    show() {
        if (!this.isVisible) {
            this.suggestionsList.classList.add('show');
            this.suggestionsList.style.display = 'block';
            this.isVisible = true;
        }
    }
    
    hide() {
        if (this.isVisible) {
            this.suggestionsList.classList.remove('show');
            this.suggestionsList.style.display = 'none';
            this.isVisible = false;
            this.selectedIndex = -1;
        }
    }
    
    selectNext() {
        if (this.suggestions.length === 0) return;
        
        const newIndex = this.selectedIndex < this.suggestions.length - 1 
            ? this.selectedIndex + 1 
            : 0;
        
        this.setSelectedIndex(newIndex);
    }
    
    selectPrevious() {
        if (this.suggestions.length === 0) return;
        
        const newIndex = this.selectedIndex > 0 
            ? this.selectedIndex - 1 
            : this.suggestions.length - 1;
        
        this.setSelectedIndex(newIndex);
    }
    
    setSelectedIndex(index) {
        // Remover selección anterior
        const previousSelected = this.suggestionsList.querySelector('.active');
        if (previousSelected) {
            previousSelected.classList.remove('active');
        }
        
        this.selectedIndex = index;
        
        // Agregar nueva selección
        if (index >= 0 && index < this.suggestionsList.children.length) {
            const newSelected = this.suggestionsList.children[index];
            newSelected.classList.add('active');
            
            // Scroll si es necesario
            this.scrollToSelected(newSelected);
        }
    }
    
    scrollToSelected(element) {
        const container = this.suggestionsList;
        const elementTop = element.offsetTop;
        const elementBottom = elementTop + element.offsetHeight;
        const containerTop = container.scrollTop;
        const containerBottom = containerTop + container.offsetHeight;
        
        if (elementTop < containerTop) {
            container.scrollTop = elementTop;
        } else if (elementBottom > containerBottom) {
            container.scrollTop = elementBottom - container.offsetHeight;
        }
    }
    
    selectSuggestion(suggestion) {
        this.input.value = suggestion.valor;
        this.hide();
        
        // Disparar evento de cambio
        const event = new Event('change', { bubbles: true });
        this.input.dispatchEvent(event);
        
        // Disparar evento personalizado
        const customEvent = new CustomEvent('autocomplete:select', {
            detail: { suggestion, tipo: this.tipo }
        });
        this.input.dispatchEvent(customEvent);
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Método público para destruir la instancia
    destroy() {
        if (this.searchTimeout) {
            clearTimeout(this.searchTimeout);
        }
        
        if (this.currentRequest) {
            this.currentRequest.abort();
        }
        
        // Remover eventos
        this.input.removeEventListener('input', this.handleInput);
        this.input.removeEventListener('keydown', this.handleKeydown);
        this.input.removeEventListener('focus', this.handleFocus);
        this.input.removeEventListener('blur', this.handleBlur);
        
        // Restaurar DOM
        this.input.classList.remove('autocomplete-input');
        this.container.parentNode.insertBefore(this.input, this.container);
        this.container.remove();
    }
}

// Función de inicialización automática
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar autocompletado para observaciones
    const observacionesInput = document.getElementById('observaciones');
    if (observacionesInput) {
        new Autocomplete(observacionesInput, 'observaciones');
    }
    
    // Inicializar autocompletado para comisionamiento
    const comisionamientoInput = document.getElementById('comisionamiento');
    if (comisionamientoInput) {
        new Autocomplete(comisionamientoInput, 'comisionamiento');
    }
});

// Exportar para uso manual
if (typeof module !== 'undefined' && module.exports) {
    module.exports = Autocomplete;
} else if (typeof window !== 'undefined') {
    window.Autocomplete = Autocomplete;
}