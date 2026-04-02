<div class="accessibility-panel" id="accessibility-panel" data-accessibility-panel hidden>
    <div class="accessibility-panel__backdrop" data-accessibility-close></div>
    <section class="accessibility-panel__card" aria-label="Accessibility settings">
        <div class="accessibility-panel__header">
            <div>
                <div class="section-pill">Accessibility</div>
                <h2>Display settings</h2>
                <p>Adjust text size, contrast, and motion preferences for this device.</p>
            </div>
            <button type="button" class="btn-close" data-accessibility-close aria-label="Close accessibility settings"></button>
        </div>

        <div class="accessibility-setting">
            <div class="accessibility-setting__label">Text size</div>
            <div class="accessibility-option-row" role="group" aria-label="Text size">
                <button type="button" class="accessibility-option" data-a11y-setting="textSize" data-a11y-value="compact">Smaller</button>
                <button type="button" class="accessibility-option" data-a11y-setting="textSize" data-a11y-value="default">Default</button>
                <button type="button" class="accessibility-option" data-a11y-setting="textSize" data-a11y-value="large">Larger</button>
            </div>
        </div>

        <div class="accessibility-setting">
            <div class="accessibility-setting__label">Contrast</div>
            <div class="accessibility-option-row" role="group" aria-label="Contrast">
                <button type="button" class="accessibility-option" data-a11y-setting="contrast" data-a11y-value="default">Standard</button>
                <button type="button" class="accessibility-option" data-a11y-setting="contrast" data-a11y-value="high">High</button>
            </div>
        </div>

        <div class="accessibility-setting">
            <div class="accessibility-setting__label">Motion</div>
            <div class="accessibility-option-row" role="group" aria-label="Motion">
                <button type="button" class="accessibility-option" data-a11y-setting="motion" data-a11y-value="default">Standard</button>
                <button type="button" class="accessibility-option" data-a11y-setting="motion" data-a11y-value="reduced">Reduced</button>
            </div>
        </div>

        <div class="accessibility-panel__footer">
            <button type="button" class="btn btn-outline-secondary" data-a11y-reset>
                <i class="fas fa-rotate-left"></i>
                <span>Reset settings</span>
            </button>
        </div>
    </section>
</div>
