<?php declare(strict_types = 1);

namespace PHPStan\Type\Php;

use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Type\NullType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\TypeUtils;

class ArrayValueFirstDynamicReturnTypeExtension implements \PHPStan\Type\DynamicFunctionReturnTypeExtension
{

	public function isFunctionSupported(FunctionReflection $functionReflection): bool
	{
		return $functionReflection->getName() === 'array_value_first';
	}

	public function getTypeFromFunctionCall(FunctionReflection $functionReflection, FuncCall $functionCall, Scope $scope): Type
	{
		if (!isset($functionCall->args[0])) {
			return ParametersAcceptorSelector::selectSingle($functionReflection->getVariants())->getReturnType();
		}

		$argType = $scope->getType($functionCall->args[0]->value);
		$iterableAtLeastOnce = $argType->isIterableAtLeastOnce();
		if ($iterableAtLeastOnce->no()) {
			return new NullType();
		}

		$constantArrays = TypeUtils::getConstantArrays($argType);
		if (count($constantArrays) > 0) {
			$valueTypes = [];
			foreach ($constantArrays as $constantArray) {
				$arrayValueTypes = $constantArray->getValueTypes();
				if (count($arrayValueTypes) === 0) {
					$valueTypes[] = new NullType();
					continue;
				}

				$valueTypes[] = $arrayValueTypes[0];
			}

			return TypeCombinator::union(...$valueTypes);
		}

		$valueType = $argType->getIterableValueType();
		if ($iterableAtLeastOnce->yes()) {
			return $valueType;
		}

		return TypeCombinator::union($valueType, new NullType());
	}

}
