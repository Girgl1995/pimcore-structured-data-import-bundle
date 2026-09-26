<?php

declare(strict_types=1);

namespace Factotum\StructuredDataImportBundle\Controller;

use Factotum\StructuredDataImportBundle\Mapping\Type\TransformationStructuredDataTypeService;
use Pimcore\Controller\UserAwareController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class ConfigDataObjectController extends UserAwareController
{
    private const TRANSFORMATION_TARGET_TYPE_ARRAY       = 'array';
    private const PARAM_CLASS_ID                         = 'class_id';
    private const PARAM_TRANSFORMATION_TARGET_TYPE_ARRAY = 'transformation_result_type';
    private const ATTRIBUTES_KEY                         = 'attributes';

    /**
     * @param Request $request
     * @param TransformationStructuredDataTypeService $transformationStructuredDataTypeService
     * @return JsonResponse
     */
    #[Route('/admin/structuredimport/load-structured-data-object-attributes', name: 'pimcore_dataimporter_configdataobject_loadstructureddataobjectattributes', options: ['expose' => true], methods: ['GET'])]
    public function loadDataObjectAttributesAction(Request $request, TransformationStructuredDataTypeService $transformationStructuredDataTypeService): JsonResponse
    {
        $classId                  = $request->query->get(self::PARAM_CLASS_ID);
        $transformationTargetType = $request->query->get(self::PARAM_TRANSFORMATION_TARGET_TYPE_ARRAY);

        if (empty($classId) || $transformationTargetType !== self::TRANSFORMATION_TARGET_TYPE_ARRAY) {
            return new JsonResponse([]);
        }

        return new JsonResponse([
            self::ATTRIBUTES_KEY => $transformationStructuredDataTypeService->getStructuredPimcoreDataTypes($classId, $transformationTargetType)
        ]);
    }
}
