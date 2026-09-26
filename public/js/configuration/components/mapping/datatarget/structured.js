pimcore.registerNS("pimcore.plugin.pimcoreDataImporterBundle.configuration.components.mapping.datatarget.structured");
pimcore.plugin.pimcoreDataImporterBundle.configuration.components.mapping.datatarget.structured = Class.create(pimcore.plugin.pimcoreDataImporterBundle.configuration.components.abstractOptionType, {

    type: 'structured',
    dataApplied: false,
    dataObjectClassId: null,
    transformationResultType: null,
    overWriteModeStoreData: null,

    isTransformationResultTypeValid: function (transformationResultType) {
        const validTypes = ['array'];
        return validTypes.includes(transformationResultType);
    },

    buildSettingsForm: function () {

        if (!this.form) {
            this.dataObjectClassId = this.configItemRootContainer.currentDataValues.dataObjectClassId;
            this.transformationResultType = this.initContext.mappingConfigItemContainer.currentDataValues.transformationResultType;
            this.validTransformationResultType = this.isTransformationResultTypeValid(this.initContext.mappingConfigItemContainer.currentDataValues.transformationResultType);

            const errorField = Ext.create('Ext.form.Label', {
                html: '<p>' + t('plugin_pimcore_datahub_data_importer_configpanel_structured_type_error') + '</p>',
                style: 'color: #cf4c35'
            });

            const errorFieldExtMessage = Ext.create('Ext.form.Label', {
                html: t('plugin_pimcore_datahub_data_importer_configpanel_structured_type'),
                style: 'padding-bottom: 5px',
            });

            const fieldContainerError = Ext.create('Ext.form.FieldContainer', {
                hidden: this.validTransformationResultType,
                items: [errorField, errorFieldExtMessage]
            });

            const languageSelection = Ext.create('Ext.form.ComboBox', {
                store: pimcore.settings.websiteLanguages,
                forceSelection: true,
                fieldLabel: t('language'),
                name: this.dataNamePrefix + 'language',
                value: this.data.language,
                allowBlank: true,
                hidden: true
            });

            const overwriteMode = Ext.create('Ext.form.ComboBox', {
                fieldLabel: t('plugin_pimcore_datahub_data_importer_configpanel_dataTarget.type_structured_write_settings_overwriteMode'),
                name: this.dataNamePrefix + 'overwriteMode',
                value: this.data.overwriteMode || 'replace',
                store: [
                    ['replace', t('plugin_pimcore_datahub_data_importer_configpanel_dataTarget.type_structured_write_settings_overwriteMode_replace')],
                    ['merge', t('plugin_pimcore_datahub_data_importer_configpanel_dataTarget.type_structured_write_settings_overwriteMode_merge')],
                ],
                hidden: !this.validTransformationResultType || (this.data.hasOwnProperty('writeIfTargetIsNotEmpty') ? !this.data.writeIfTargetIsNotEmpty : false)
            });
            this.overWriteModeStoreData = overwriteMode.store.data.items.slice();

            const attributeSelection = Ext.create('Ext.form.ComboBox', {
                displayField: 'title',
                valueField: 'key',
                queryMode: 'local',
                forceSelection: true,
                fieldLabel: t('plugin_pimcore_datahub_data_importer_configpanel_fieldName'),
                name: this.dataNamePrefix + 'fieldName',
                value: this.data.fieldName,
                allowBlank: false,
                msgTarget: 'under',
                hidden: !this.validTransformationResultType
            });

            const attributeStore = Ext.create('Ext.data.JsonStore', {
                fields: ['key', 'name', 'localized', 'className'],
                listeners: {
                    dataChanged: function (store) {
                        if (!this.dataApplied) {
                            attributeSelection.setValue(this.data.fieldName);
                            if (this.form) {
                                this.form.isValid();
                            }
                            this.dataApplied = true;
                            this.setOptionsVisibility(attributeStore, attributeSelection, languageSelection);
                        }

                        if (!store || !store.findRecord('key', attributeSelection.getValue())) {
                            attributeSelection.setValue(null);
                            this.form.isValid();
                        }
                    }.bind(this)
                }
            });

            attributeSelection.setStore(attributeStore);
            attributeSelection.on('change', this.setOptionsVisibility.bind(this, attributeStore, attributeSelection, languageSelection, overwriteMode));

            this.initContext.mappingConfigItemContainer.on(pimcore.plugin.pimcoreDataImporterBundle.configuration.events.transformationResultTypeChanged, function (newType) {

                this.validTransformationResultType = this.isTransformationResultTypeValid(newType);
                this.transformationResultType = newType;

                if (this.validTransformationResultType) {
                    attributeSelection.show();
                    languageSelection.show();
                    overwriteMode.show();
                    fieldContainerCB.show();
                    fieldContainerError.hide()
                    this.initAttributeStore(attributeStore);
                } else {
                    attributeSelection.setValue('');
                    attributeSelection.hide();
                    languageSelection.hide();
                    overwriteMode.hide();
                    fieldContainerCB.hide();
                    fieldContainerError.show();
                }
            }.bind(this));
            this.configItemRootContainer.on(pimcore.plugin.pimcoreDataImporterBundle.configuration.events.classChanged,
                function (combo, newValue, oldValue) {
                    this.dataObjectClassId = newValue;
                    this.initAttributeStore(attributeStore);
                }.bind(this)
            );

            const writeIfTargetIsNotEmpty = Ext.create('Ext.form.Checkbox', {
                boxLabel: t('plugin_pimcore_datahub_data_importer_configpanel_dataTarget.type_structured_write_settings_ifTargetIsNotEmpty'),
                name: this.dataNamePrefix + 'writeIfTargetIsNotEmpty',
                value: this.data.hasOwnProperty('writeIfTargetIsNotEmpty') ? this.data.writeIfTargetIsNotEmpty : true,
                inputValue: true,
                uncheckedValue: false,
                listeners: {
                    change: function (checkbox, value) {
                        if (value) {
                            writeIfSourceIsEmpty.setReadOnly(false);
                            writeIfSourceIsEmpty.setValue(true);
                            overwriteMode.setHidden(false);
                        } else {
                            writeIfSourceIsEmpty.setReadOnly(true);
                            writeIfSourceIsEmpty.setValue(false);
                            overwriteMode.setHidden(true);
                        }
                    }
                }
            });

            const writeIfSourceIsEmpty = Ext.create('Ext.form.Checkbox', {
                boxLabel: t('plugin_pimcore_datahub_data_importer_configpanel_dataTarget.type_structured_write_settings_ifSourceIsEmpty'),
                name: this.dataNamePrefix + 'writeIfSourceIsEmpty',
                value: this.data.hasOwnProperty('writeIfSourceIsEmpty') ? this.data.writeIfSourceIsEmpty : true,
                readOnly: this.data.hasOwnProperty('writeIfTargetIsNotEmpty') ? !this.data.writeIfTargetIsNotEmpty : false,
                inputValue: true,
                uncheckedValue: false
            });

            const fieldContainerCB = Ext.create('Ext.form.FieldContainer', {
                fieldLabel: t('plugin_pimcore_datahub_data_importer_configpanel_dataTarget.type_structured_write_settings_label'),
                defaultType: 'checkboxfield',
                hidden: !this.validTransformationResultType,
                items: [writeIfTargetIsNotEmpty, writeIfSourceIsEmpty]
            });

            this.form = Ext.create('DataHub.DataImporter.StructuredValueForm', {
                defaults: {
                    labelWidth: 120,
                    width: 500,
                    listeners: {
                        errorchange: this.initContext.updateValidationStateCallback
                    }
                },
                border: false,
                items: [
                    fieldContainerError,
                    attributeSelection,
                    languageSelection,
                    fieldContainerCB,
                    overwriteMode
                ]
            });

            this.initAttributeStore(attributeStore);
        }

        return this.form;
    },

    initAttributeStore: function (attributeStore) {
        const classId = this.dataObjectClassId;

        const transformationResultType = this.transformationResultType;

        let targetFieldCache = this.configItemRootContainer.targetFieldCacheRelations || {};

        if (targetFieldCache[classId] && targetFieldCache[classId][transformationResultType]) {

            if (targetFieldCache[classId][transformationResultType].loading) {
                setTimeout(this.initAttributeStore.bind(this, attributeStore), 400);
            } else {
                attributeStore.loadData(targetFieldCache[classId][transformationResultType].data);
            }

        } else {
            targetFieldCache = targetFieldCache || {};
            targetFieldCache[classId] = targetFieldCache[classId] || {};
            targetFieldCache[classId][transformationResultType] = {
                loading: true,
                data: null
            };
            this.configItemRootContainer.targetFieldCacheRelations = targetFieldCache;

            Ext.Ajax.request({
                url: Routing.generate('pimcore_dataimporter_configdataobject_loadstructureddataobjectattributes'),
                method: 'GET',
                params: {
                    'class_id': classId,
                    'transformation_result_type': transformationResultType,
                },
                success: function (response) {
                    let data = Ext.decode(response.responseText);
       
                    targetFieldCache[classId][transformationResultType].loading = false;
                    targetFieldCache[classId][transformationResultType].data = data.attributes;

                    attributeStore.loadData(targetFieldCache[classId][transformationResultType].data);
                }.bind(this)
            });
        }
    },

    setOptionsVisibility: function (attributeStore, attributeSelection, languageSelection) {
        const record = attributeStore.findRecord('key', attributeSelection.getValue());
        if (record) {
            languageSelection.setHidden(!record.data.localized);
        }
    }
    });
    
