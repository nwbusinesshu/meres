<div class="modal fade" id="bonus-config-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ __('admin/bonuses.configure-multipliers') }}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-3">
                    {{ __('admin/bonuses.multiplier-help-text') }}
                </p>
                <div class="table-responsive">
                    <table class="table table-sm" id="multiplier-table">
                        <thead>
                            <tr>
                                <th style="width: 80px;">{{ __('admin/bonuses.level') }}</th>
                                <th style="width: 120px;">{{ __('admin/bonuses.category') }}</th>
                                <th>{{ __('admin/bonuses.multiplier') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Populated by JS -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="resetMultipliersToDefault()">
                    <i class="fa fa-undo"></i> {{ __('admin/bonuses.reset-defaults') }}
                </button>
                <button type="button" class="btn btn-primary" onclick="saveMultiplierConfig()">
                    <i class="fa fa-save"></i> {{ __('global.save') }}
                </button>
            </div>
        </div>
    </div>
</div>