
# Feature Implementation: Users CRUD API

## Overview
- **Feature**: Boss Pods — Users CRUD API (Option 1)
- **Implementation Date**: January 23, 2026
- **Files Created**: 6
- **Files Modified**: 4
- **Lines Added**: ~900
- **Lines Removed**: 0

## Files Created

### Services

#### `app/Services/UserService.php`
- **Purpose**: Encapsula toda a lógica de negócio para operações de usuários
- **Key Components**:
  - `list()`: Lista usuários com filtro por role (Admin vê todos, Manager vê apenas mesma loja), filtros opcionais (role, store_id para Admin, is_active), paginação
  - `find()`: Busca usuário por ID
  - `create()`: Cria novo usuário (password hashado via model cast)
  - `update()`: Atualiza usuário (password só atualizado quando fornecido)
  - `delete()`: Soft-delete de usuário
- **Dependencies**: `App\Models\User`
- **Error Handling**: Trata QueryException para unique constraint (email) retornando RuntimeException com código 422

### Controllers

#### `app/Http/Controllers/Api/V1/UserController.php`
- **Purpose**: Controller thin que delega para UserService
- **Key Components**:
  - `index()`: GET /api/v1/users - Lista usuários com filtros por role e store, paginação
  - `show()`: GET /api/v1/users/{user} - Retorna usuário específico
  - `store()`: POST /api/v1/users - Cria novo usuário
  - `update()`: PUT /api/v1/users/{user} - Atualiza usuário
  - `destroy()`: DELETE /api/v1/users/{user} - Soft-delete usuário (previne self-delete)
- **Dependencies**: `UserService`, `CreateUserRequest`, `UpdateUserRequest`, `UserResource`
- **Authorization**: Usa `Gate::authorize()` para todas as ações via UserPolicy
- **Special Features**: 
  - Eager loading de `store` em todas as respostas
  - Prevenção de self-delete no destroy (retorna 403)
  - Validação de query params (page, per_page, role, store_id, is_active)

### Form Requests

#### `app/Http/Requests/Api/V1/CreateUserRequest.php`
- **Purpose**: Validação de criação de usuário
- **Validation Rules**:
  - `name`: required|string|max:255
  - `email`: required|email|unique:users,email
  - `password`: required|string|min:8
  - `role`: required|string|in:admin,manager,seller
  - `store_id`: nullable|integer|exists:stores,id|requiredIf(role in [manager, seller])
  - `is_active`: boolean
- **Authorization**: Verifica permissão `create` em User via Policy (Admin only)
- **Messages**: Mensagens customizadas para todos os campos
- **Special**: Validação condicional de `store_id` usando `Rule::requiredIf()` - obrigatório para manager e seller

#### `app/Http/Requests/Api/V1/UpdateUserRequest.php`
- **Purpose**: Validação de atualização de usuário
- **Validation Rules**:
  - `name`: sometimes|string|max:255
  - `email`: sometimes|email|unique:users,email,{user->id}
  - `password`: sometimes|string|min:8
  - `role`: sometimes|string|in:admin,manager,seller
  - `store_id`: nullable|integer|exists:stores,id|requiredIf(role in [manager, seller])
  - `is_active`: boolean
- **Authorization**: Verifica permissão `update` no usuário específico via Policy (Admin only)
- **Special**: 
  - Validação condicional de `store_id` baseada no role (atual ou novo se fornecido)
  - Password só validado quando fornecido (não limpa senha existente se omitido)

### Policies

#### `app/Policies/UserPolicy.php`
- **Purpose**: Autorização por role para operações de usuários
- **Rules**:
  - `viewAny`: Admin ou Manager (Seller retorna false)
  - `view`: Admin (qualquer), Manager (mesma loja), Seller (false)
  - `create`: Admin only
  - `update`: Admin only
  - `delete`: Admin only
- **Special**: Filtro por loja para Manager é feito no service, mas policy também valida

### Test Files

#### `tests/Unit/UserTest.php`
- **Coverage**:
  - Scopes: `admins()`, `managers()`, `sellers()`, `forStore()`
  - Helpers: `isAdmin()`, `isManager()`, `isSeller()`
  - Relacionamentos: store, sales
  - Soft deletes

#### `tests/Feature/UserControllerTest.php`
- **Coverage**:
  - GET /api/v1/users: Admin, Manager (mesma loja), Seller (403), filtros (role, store_id), paginação
  - GET /api/v1/users/{user}: Admin, Manager (mesma loja/outra loja 403), 404
  - POST /api/v1/users: Admin cria, Manager/Seller 403, validação store_id para manager/seller, duplicata email 422
  - PUT /api/v1/users/{user}: Admin atualiza, Manager 403, password só quando fornecido, validação store_id
  - DELETE /api/v1/users/{user}: Admin soft-delete, Manager 403, self-delete 403
  - Autenticação: 401 sem token
  - Validação de query params

## Files Modified

### `routes/api.php`
- **Changes**: Adicionadas rotas para users
- **Routes Added**:
  - GET /api/v1/users (index)
  - POST /api/v1/users (store)
  - GET /api/v1/users/{user} (show)
  - PUT /api/v1/users/{user} (update)
  - DELETE /api/v1/users/{user} (destroy)
- **Middleware**: Apenas `auth:sanctum` (sem `store.access` - users são globais)
- **Import**: Adicionado `use App\Http\Controllers\Api\V1\UserController;`

### `app/Providers/AppServiceProvider.php`
- **Changes**: Registrada UserPolicy
- **Before**: Apenas policies de Store, Product, StoreProduct, Sale, Customer
- **After**: Adicionado `Gate::policy(User::class, UserPolicy::class);`
- **Import**: Adicionado `use App\Models\User;` e `use App\Policies\UserPolicy;`

### `app/Models/User.php`
- **Changes**: Adicionados type hints de retorno nos scopes
- **Before**: Scopes sem type hints
- **After**: 
  - `scopeAdmins()`: `\Illuminate\Database\Eloquent\Builder`
  - `scopeManagers()`: `\Illuminate\Database\Eloquent\Builder`
  - `scopeSellers()`: `\Illuminate\Database\Eloquent\Builder`
  - `scopeForStore()`: `\Illuminate\Database\Eloquent\Builder`
- **Impact**: Consistência com padrões do projeto e melhor suporte do IDE

### `app/Http/Resources/Api/V1/UserResource.php`
- **Status**: Já existia e está correto
- **Fields**: id, name, email, role, store_id, is_active, store (opcional), created_at, updated_at
- **Security**: Nunca expõe password ou remember_token (já estava correto)

## API Endpoints

### GET /api/v1/users
- **Description**: Lista usuários com filtro por role e loja
- **Query Parameters**:
  - `page`: integer|min:1 (opcional)
  - `per_page`: integer|min:1|max:100 (opcional, default 15)
  - `role`: string|in:admin,manager,seller (opcional, filtro por role)
  - `store_id`: integer|exists:stores,id (opcional, apenas Admin pode usar)
  - `is_active`: boolean (opcional, filtro por status ativo)
- **Response**: 200 com paginação
- **Authorization**: 
  - Admin: vê todos os usuários
  - Manager: vê apenas usuários da mesma loja
  - Seller: 403 (sem acesso)

### GET /api/v1/users/{user}
- **Description**: Retorna usuário específico
- **Response**: 200 com dados do usuário (incluindo store se disponível)
- **Authorization**: 
  - Admin: qualquer usuário
  - Manager: apenas usuários da mesma loja (403 caso contrário)
  - Seller: 403

### POST /api/v1/users
- **Description**: Cria novo usuário
- **Body**: { name: string, email: string, password: string, role: string, store_id?: integer, is_active?: boolean }
- **Response**: 201 com dados do usuário criado
- **Validation**: 
  - name, email, password, role obrigatórios
  - email único
  - password mínimo 8 caracteres
  - store_id obrigatório quando role é manager ou seller
  - store_id opcional (geralmente null) quando role é admin
- **Authorization**: Admin only (403 para Manager/Seller)
- **Business Rule**: Password é hashado automaticamente via model cast

### PUT /api/v1/users/{user}
- **Description**: Atualiza usuário existente
- **Body**: { name?: string, email?: string, password?: string, role?: string, store_id?: integer, is_active?: boolean }
- **Response**: 200 com dados do usuário atualizado
- **Validation**: 
  - Todos os campos opcionais (sometimes)
  - email único (ignorando usuário atual)
  - password só validado quando fornecido (não limpa senha existente)
  - store_id obrigatório quando role (atual ou novo) é manager ou seller
- **Authorization**: Admin only (403 para Manager/Seller)
- **Business Rule**: Password só é atualizado quando fornecido no request

### DELETE /api/v1/users/{user}
- **Description**: Soft-delete de usuário
- **Response**: 200 com mensagem de sucesso
- **Authorization**: Admin only (403 para Manager/Seller)
- **Business Rule**: Admin não pode deletar a si mesmo (403 com mensagem "You cannot delete your own account.")
- **Side Effect**: `deleted_at` é definido (soft delete)

## Database Changes

#### Migrations
- Nenhuma migration nova (usa tabela `users` existente)

## Implementation Decisions

1. **Role-Based Filtering**: Filtro por role implementado no service (Manager vê apenas mesma loja, Seller não tem acesso)
2. **Store Assignment Rules**: store_id obrigatório para manager e seller, opcional para admin (validação condicional nos Form Requests)
3. **Password Management**: Hash automático via model cast (`password => 'hashed'`); password só atualizado quando fornecido no update
4. **Self-Delete Prevention**: Verificação no controller antes de chamar service (retorna 403 se tentar deletar a si mesmo)
5. **Soft Delete**: Usa SoftDeletes do modelo User para preservar dados históricos
6. **Eager Loading**: Store é eager loaded em todas as respostas para incluir informações da loja quando disponível
7. **Email Uniqueness**: Validação de email único em create e update (ignorando usuário atual no update)
8. **Admin store_id**: No update, quando role é ou passa a ser admin, `UserService::update` define `store_id = null` automaticamente (Plan: "When role is admin, store_id null")

## Testing

#### Test Files Created

##### `tests/Unit/UserTest.php`
- **Coverage**:
  - Scopes: `admins()`, `managers()`, `sellers()`, `forStore()` retornam apenas usuários corretos
  - Helpers: `isAdmin()`, `isManager()`, `isSeller()` retornam true/false corretamente
  - Relacionamentos: store, sales funcionam corretamente
  - Soft deletes funcionam corretamente

##### `tests/Feature/UserControllerTest.php`
- **Coverage**:
  - GET /api/v1/users: Admin, Manager (mesma loja), Seller (403), filtros (role, store_id), paginação, validação de query params
  - GET /api/v1/users/{user}: Admin, Manager (mesma loja/outra loja 403), 404
  - POST /api/v1/users: Admin cria, Manager/Seller 403, validação store_id para manager/seller, admin sem store_id ok, duplicata email 422
  - PUT /api/v1/users/{user}: Admin atualiza, Manager 403, password só quando fornecido, validação store_id
  - DELETE /api/v1/users/{user}: Admin soft-delete, Manager 403, self-delete 403

#### Test Coverage
- **Unit Tests**: 9 testes para modelo User
- **Feature Tests**: 18 testes cobrindo todos os endpoints e cenários
- **Total**: 27+ testes implementados
- **Coverage Target**: 80%+ (conforme Backend.md)

## Performance Considerations

- Paginação implementada para prevenir queries grandes (max 100 por página)
- Índices existentes em `users` (unique email, index store_id, index role) otimizam queries
- Eager loading de store em todas as respostas para evitar N+1 queries
- Filtros aplicados via scopes do Eloquent para eficiência

## Integration Points

- **Reutiliza**: `UserResource` (já existente, usado em auth), modelos `User`, `Store`
- **Compatível com**: Option B (Stores & Products), Option 1 (Store Products), Option A (Customers), Sales API - users são a base do sistema de autenticação e autorização
- **Preparado para**: Auditoria futura (log de criações/atualizações/deletes de usuários)

---

# Feature Implementation: Polish (Search, Sorting, Cache)

## Overview
- **Feature**: Boss Pods — Option 2: Polish (Search, Sorting, Cache)
- **Implementation Date**: January 24, 2026
- **Files Created**: 8
- **Files Modified**: 6
- **Lines Added**: ~2000
- **Lines Removed**: 0

## Files Created

### Services

#### `app/Services/CacheService.php`
- **Purpose**: Centraliza gerenciamento de cache para list e show endpoints
- **Key Components**:
  - `getList()`: Obtém dados de listagem do cache ou executa callback, com TTL configurável
  - `getShow()`: Obtém dados de show do cache ou executa callback, com TTL configurável
  - `invalidateList()`: Invalida caches de listagem para um recurso (com suporte a padrões para Redis)
  - `invalidateShow()`: Invalida cache de show para um recurso específico
- **Cache Key Format**:
  - List: `bp:list:{resource}:{hash}` onde hash inclui filtros, user context (user_id, role, store_id), per_page
  - Show: `bp:show:{resource}:{id}`
- **TTL**: Configurável via `config/boss_pods.cache.ttl.list` (120s) e `config/boss_pods.cache.ttl.show` (300s)
- **Security**: Chaves de cache nunca incluem input não validado; apenas parâmetros whitelisted e contexto de autenticação

#### `app/Services/StoreService.php`
- **Purpose**: Service para operações de stores com search e sort
- **Key Components**:
  - `list()`: Lista stores com search (name), sort (name, is_active, created_at), filtros existentes, paginação
  - `find()`, `create()`, `update()`, `delete()`: Operações CRUD básicas
- **Search**: Busca parcial case-insensitive em `name` (ILIKE para PostgreSQL, LOWER LIKE para SQLite/MySQL)
- **Sort**: Whitelist `['name', 'is_active', 'created_at']`, default `name asc`

#### `app/Services/ProductService.php`
- **Purpose**: Service para operações de products com search e sort
- **Key Components**:
  - `list()`: Lista products com search (brand, name, flavor), sort (brand, name, flavor, created_at), filtro brand existente, paginação
  - `find()`, `create()`, `update()`, `delete()`: Operações CRUD básicas
- **Search**: Busca parcial case-insensitive em `brand`, `name` ou `flavor` (OR)
- **Sort**: Whitelist `['brand', 'name', 'flavor', 'created_at']`, default `brand asc`

#### `app/Services/StoreProductService.php`
- **Purpose**: Service para operações de store products com search e sort
- **Key Components**:
  - `list()`: Lista store products com search via relacionamento `product` (brand, name, flavor), sort incluindo `product_name` (via join), filtros existentes, paginação
  - `find()`, `create()`, `update()`, `delete()`: Operações CRUD básicas
- **Search**: Busca via `whereHas('product')` em brand, name, flavor
- **Sort**: Whitelist `['stock_quantity', 'sale_price', 'created_at', 'product_name']`, default `product_name asc`
- **Special**: `product_name` sort requer join com tabela `products`

#### `app/Services/CustomerService.php`
- **Purpose**: Service para operações de customers com sort (search já existia)
- **Key Components**:
  - `list()`: Lista customers com search existente (name, phone), sort (name, phone, total_purchases, created_at), filtro phone exato, paginação
  - `find()`, `create()`, `update()`: Operações CRUD básicas
- **Search**: Mantido existente (busca em name e phone)
- **Sort**: Whitelist `['name', 'phone', 'total_purchases', 'created_at']`, default `name asc`

#### `app/Services/SaleService.php`
- **Purpose**: Service para operações de sales com search opcional e sort
- **Key Components**:
  - `list()`: Lista sales com search opcional em `notes`, sort (sale_date, total_amount, created_at), filtros existentes (role-based, date range), paginação
  - `find()`, `createSale()`: Operações básicas
- **Search**: Opcional em `notes` (parcial case-insensitive)
- **Sort**: Whitelist `['sale_date', 'total_amount', 'created_at']`, default `sale_date desc`
- **Cache**: Sales não usam cache (ou TTL muito curto 30s) devido a alta rotatividade

#### `app/Services/UserService.php`
- **Purpose**: Service para operações de users com search e sort
- **Key Components**:
  - `list()`: Lista users com search (name, email), sort (name, email, role, created_at), filtros existentes (role-based, store_id, is_active), paginação
  - `find()`, `create()`, `update()`, `delete()`: Operações CRUD básicas
- **Search**: Busca parcial case-insensitive em `name` ou `email` (OR)
- **Sort**: Whitelist `['name', 'email', 'role', 'created_at']`, default `name asc`

### Controllers

#### `app/Http/Controllers/Api/V1/StoreController.php`
- **Purpose**: Controller thin que delega para StoreService e usa CacheService
- **Key Components**:
  - `index()`: Valida search, sort_by, sort_order; usa cache para list; retorna StoreResource collection
  - `show()`: Usa cache para show; retorna StoreResource
  - `store()`, `update()`, `destroy()`: Invalida cache após operações
- **Validation**: search (string|max:255), sort_by (in:name,is_active,created_at), sort_order (in:asc,desc)

#### `app/Http/Controllers/Api/V1/ProductController.php`
- **Purpose**: Controller thin que delega para ProductService e usa CacheService
- **Key Components**:
  - `index()`: Valida search, sort_by, sort_order; usa cache para list; retorna ProductResource collection
  - `show()`: Usa cache para show; retorna ProductResource
  - `store()`, `update()`, `destroy()`: Invalida cache após operações
- **Validation**: search (string|max:255), sort_by (in:brand,name,flavor,created_at), sort_order (in:asc,desc)

#### `app/Http/Controllers/Api/V1/StoreProductController.php`
- **Purpose**: Controller thin que delega para StoreProductService e usa CacheService
- **Key Components**:
  - `index()`: Valida search, sort_by, sort_order; usa cache para list com resource key `stores.{store}.products`; retorna StoreProductResource collection
  - `show()`: Usa cache para show; retorna StoreProductResource
  - `store()`, `update()`, `destroy()`: Invalida cache após operações
- **Validation**: search (string|max:255), sort_by (in:stock_quantity,sale_price,created_at,product_name), sort_order (in:asc,desc)

#### `app/Http/Controllers/Api/V1/CustomerController.php`
- **Purpose**: Controller thin que delega para CustomerService e usa CacheService
- **Key Components**:
  - `index()`: Valida sort_by, sort_order; usa cache para list; retorna CustomerResource collection
  - `show()`: Usa cache para show; retorna CustomerResource
  - `store()`, `update()`: Invalida cache após operações
- **Validation**: sort_by (in:name,phone,total_purchases,created_at), sort_order (in:asc,desc)

#### `app/Http/Controllers/Api/V1/SaleController.php`
- **Purpose**: Controller thin que delega para SaleService (sem cache para sales)
- **Key Components**:
  - `index()`: Valida search (notes), sort_by, sort_order; não usa cache; retorna SaleResource collection
  - `show()`: Não usa cache; retorna SaleResource
  - `store()`: Cria sale sem invalidar cache (sales não são cacheados)
- **Validation**: search (string|max:255), sort_by (in:sale_date,total_amount,created_at), sort_order (in:asc,desc)
- **Special**: Sales não usam cache devido a alta rotatividade

#### `app/Http/Controllers/Api/V1/UserController.php`
- **Purpose**: Controller thin que delega para UserService e usa CacheService
- **Key Components**:
  - `index()`: Valida search, sort_by, sort_order; usa cache para list; retorna UserResource collection
  - `show()`: Usa cache para show; retorna UserResource
  - `store()`, `update()`, `destroy()`: Invalida cache após operações
- **Validation**: search (string|max:255), sort_by (in:name,email,role,created_at), sort_order (in:asc,desc)

### Configuration

#### `config/boss_pods.php`
- **Purpose**: Configuração centralizada para funcionalidades do Boss Pods
- **Key Components**:
  - `cache.ttl.list`: TTL para caches de listagem (default 120s, configurável via `CACHE_TTL_LIST`)
  - `cache.ttl.show`: TTL para caches de show (default 300s, configurável via `CACHE_TTL_SHOW`)
  - `cache.ttl.sales`: TTL para caches de sales (default 30s, configurável via `CACHE_TTL_SALES`) - opcional
- **Environment Variables**: `CACHE_TTL_LIST`, `CACHE_TTL_SHOW`, `CACHE_TTL_SALES`

### Test Files

#### `tests/Unit/StoreServiceTest.php`
- **Coverage**: Search em name, sort por name (asc/desc), fallback para default quando sort_by inválido

#### `tests/Unit/ProductServiceTest.php`
- **Coverage**: Search em brand/name/flavor, sort por brand (asc), fallback para default

#### `tests/Unit/CustomerServiceTest.php`
- **Coverage**: Sort por name (asc), sort por total_purchases (desc), fallback para default

#### `tests/Unit/UserServiceTest.php`
- **Coverage**: Search em name/email, sort por email (asc), fallback para default

#### `tests/Unit/SaleServiceTest.php`
- **Coverage**: Search em notes, sort por sale_date (desc default), sort por total_amount (asc)

#### `tests/Unit/CacheServiceTest.php`
- **Coverage**: Cache hit/miss para list e show, invalidação de show, cache keys diferentes por user context

## Files Modified

### Services (todos recriados com search/sort)

#### `app/Services/StoreService.php`
- **Changes**: Adicionado search em `name`, sort com whitelist, defaults
- **Before**: Apenas filtros básicos (is_active) e role-based filtering
- **After**: Search + sort integrados, mantendo funcionalidades existentes

#### `app/Services/ProductService.php`
- **Changes**: Adicionado search em `brand`, `name`, `flavor`, sort com whitelist, defaults
- **Before**: Apenas filtro brand
- **After**: Search + sort integrados, mantendo filtro brand

#### `app/Services/StoreProductService.php`
- **Changes**: Adicionado search via relacionamento `product`, sort incluindo `product_name` (join), defaults
- **Before**: Apenas filtros is_active e low_stock
- **After**: Search + sort integrados, mantendo filtros existentes

#### `app/Services/CustomerService.php`
- **Changes**: Adicionado sort com whitelist, defaults; search já existia
- **Before**: Search em name/phone, sem sort
- **After**: Search mantido + sort adicionado

#### `app/Services/SaleService.php`
- **Changes**: Adicionado search opcional em `notes`, sort com whitelist, default `sale_date desc`
- **Before**: Apenas filtros role-based e date range
- **After**: Search opcional + sort integrados, mantendo filtros existentes

#### `app/Services/UserService.php`
- **Changes**: Adicionado search em `name`, `email`, sort com whitelist, defaults
- **Before**: Apenas filtros role-based, store_id, is_active
- **After**: Search + sort integrados, mantendo filtros existentes

### Controllers (todos recriados com validação e cache)

#### `app/Http/Controllers/Api/V1/StoreController.php`
- **Changes**: Adicionada validação de search/sort, integração com CacheService, invalidação de cache
- **Before**: Apenas validação básica e delegação para service
- **After**: Validação completa de search/sort, cache para list/show, invalidação em create/update/delete

#### `app/Http/Controllers/Api/V1/ProductController.php`
- **Changes**: Adicionada validação de search/sort, integração com CacheService, invalidação de cache
- **Before**: Apenas validação básica e delegação para service
- **After**: Validação completa de search/sort, cache para list/show, invalidação em create/update/delete

#### `app/Http/Controllers/Api/V1/StoreProductController.php`
- **Changes**: Adicionada validação de search/sort, integração com CacheService, invalidação de cache com resource key scoped
- **Before**: Apenas validação básica e delegação para service
- **After**: Validação completa de search/sort, cache para list/show com key `stores.{store}.products`, invalidação em create/update/delete

#### `app/Http/Controllers/Api/V1/CustomerController.php`
- **Changes**: Adicionada validação de sort, integração com CacheService, invalidação de cache
- **Before**: Apenas validação básica e delegação para service
- **After**: Validação de sort, cache para list/show, invalidação em create/update

#### `app/Http/Controllers/Api/V1/SaleController.php`
- **Changes**: Adicionada validação de search (notes) e sort, sem cache (sales não são cacheados)
- **Before**: Apenas validação básica e delegação para service
- **After**: Validação de search/sort, sem cache conforme especificação

#### `app/Http/Controllers/Api/V1/UserController.php`
- **Changes**: Adicionada validação de search/sort, integração com CacheService, invalidação de cache
- **Before**: Apenas validação básica e delegação para service
- **After**: Validação completa de search/sort, cache para list/show, invalidação em create/update/delete

### Test Files (adicionados testes para search/sort/cache)

#### `tests/Feature/StoreControllerTest.php`
- **Changes**: Adicionados testes para search, sort, cache hit/miss, invalidação
- **New Tests**:
  - `test_get_stores_with_search_filters_results`
  - `test_get_stores_with_sort_by_name_asc`
  - `test_get_stores_with_invalid_sort_by_uses_default`
  - `test_get_stores_cache_hit_on_second_request`
  - `test_get_stores_cache_invalidated_after_create`

#### `tests/Feature/ProductControllerTest.php`
- **Changes**: Adicionados testes para search, sort, cache
- **New Tests**:
  - `test_get_products_with_search_filters_results`
  - `test_get_products_with_sort_by_brand_asc`
  - `test_get_products_with_invalid_sort_by_uses_default`
  - `test_get_products_cache_hit_on_second_request`

#### `tests/Feature/CustomerControllerTest.php`
- **Changes**: Adicionados testes para sort, cache
- **New Tests**:
  - `test_get_customers_with_sort_by_name_asc`
  - `test_get_customers_with_sort_by_total_purchases_desc`
  - `test_get_customers_cache_hit_on_second_request`

#### `tests/Feature/UserControllerTest.php`
- **Changes**: Adicionados testes para search, sort, cache
- **New Tests**:
  - `test_get_users_with_search_filters_results`
  - `test_get_users_with_sort_by_email_asc`
  - `test_get_users_with_invalid_sort_by_uses_default`
  - `test_get_users_cache_hit_on_second_request`

#### `tests/Feature/SaleControllerTest.php`
- **Changes**: Adicionados testes para search (notes) e sort
- **New Tests**:
  - `test_get_sales_with_search_on_notes_filters_results`
  - `test_get_sales_with_sort_by_total_amount_asc`
  - `test_get_sales_defaults_to_sale_date_desc`

#### `tests/Feature/StoreProductControllerTest.php`
- **Changes**: Adicionados testes para search via product e sort por product_name
- **New Tests**:
  - `test_get_store_products_with_search_filters_by_product_attributes`
  - `test_get_store_products_with_sort_by_product_name_asc`
  - `test_get_store_products_with_invalid_sort_by_uses_default`

## API Endpoints - New Query Parameters

### GET /api/v1/stores
- **New Parameters**:
  - `search`: string|max:255 (busca parcial em name)
  - `sort_by`: string|in:name,is_active,created_at (default: name)
  - `sort_order`: string|in:asc,desc (default: asc)

### GET /api/v1/products
- **New Parameters**:
  - `search`: string|max:255 (busca parcial em brand, name ou flavor)
  - `sort_by`: string|in:brand,name,flavor,created_at (default: brand)
  - `sort_order`: string|in:asc,desc (default: asc)

### GET /api/v1/stores/{store}/products
- **New Parameters**:
  - `search`: string|max:255 (busca parcial em product.brand, name ou flavor)
  - `sort_by`: string|in:stock_quantity,sale_price,created_at,product_name (default: product_name)
  - `sort_order`: string|in:asc,desc (default: asc)

### GET /api/v1/customers
- **New Parameters**:
  - `sort_by`: string|in:name,phone,total_purchases,created_at (default: name)
  - `sort_order`: string|in:asc,desc (default: asc)
  - **Note**: `search` já existia, mantido

### GET /api/v1/stores/{store}/sales
- **New Parameters**:
  - `search`: string|max:255 (busca opcional parcial em notes)
  - `sort_by`: string|in:sale_date,total_amount,created_at (default: sale_date)
  - `sort_order`: string|in:asc,desc (default: desc)

### GET /api/v1/users
- **New Parameters**:
  - `search`: string|max:255 (busca parcial em name ou email)
  - `sort_by`: string|in:name,email,role,created_at (default: name)
  - `sort_order`: string|in:asc,desc (default: asc)

## Implementation Decisions

1. **Search Implementation**: 
   - Usa parameter binding para prevenir SQL injection
   - Compatível com PostgreSQL (ILIKE) e SQLite/MySQL (LOWER LIKE)
   - Busca parcial case-insensitive em todos os casos

2. **Sort Implementation**:
   - Whitelist rigorosa de colunas permitidas
   - Fallback para default quando sort_by inválido (não retorna 422, usa default)
   - sort_order validado como `in:asc,desc` com fallback para default

3. **Cache Strategy**:
   - List cache: TTL 120s (2 minutos)
   - Show cache: TTL 300s (5 minutos)
   - Sales: Sem cache (ou TTL 30s se implementado)
   - Cache keys incluem user context (user_id, role, store_id) para evitar compartilhamento entre roles diferentes
   - Invalidação: list + show em create/update/delete

4. **Cache Key Format**:
   - List: `bp:list:{resource}:{hash}` onde hash é determinístico baseado em filtros + user context
   - Show: `bp:show:{resource}:{id}`
   - Scoped resources: `bp:list:stores.{storeId}.products:{hash}`

5. **Cache Invalidation**:
   - Redis: Usa pattern matching com `keys()` para invalidação de list (com fallback se não suportado)
   - File/Database: TTL natural (não há invalidação eficiente por pattern)
   - Show: Invalidação direta por key específica

6. **Security**:
   - Search usa apenas parameter binding (nunca concatenação de strings)
   - Sort usa apenas whitelist (nunca passa input direto para orderBy)
   - Cache keys derivados apenas de inputs validados e contexto de auth

7. **Database Compatibility**:
   - Search adaptado para driver (PostgreSQL ILIKE vs SQLite/MySQL LOWER LIKE)
   - Mantém compatibilidade com testes em SQLite

## Testing

#### Test Files Created

##### `tests/Unit/StoreServiceTest.php`
- **Coverage**: Search em name, sort por name (asc/desc), fallback para default

##### `tests/Unit/ProductServiceTest.php`
- **Coverage**: Search em brand/name/flavor, sort por brand, fallback para default

##### `tests/Unit/CustomerServiceTest.php`
- **Coverage**: Sort por name e total_purchases, fallback para default

##### `tests/Unit/UserServiceTest.php`
- **Coverage**: Search em name/email, sort por email, fallback para default

##### `tests/Unit/SaleServiceTest.php`
- **Coverage**: Search em notes, sort por sale_date (desc default) e total_amount

##### `tests/Unit/CacheServiceTest.php`
- **Coverage**: Cache hit/miss para list e show, invalidação, cache keys diferentes por user context

#### Test Files Modified (Feature Tests)

##### `tests/Feature/StoreControllerTest.php`
- **New Tests**: 5 testes para search, sort, cache hit/miss, invalidação

##### `tests/Feature/ProductControllerTest.php`
- **New Tests**: 4 testes para search, sort, cache

##### `tests/Feature/CustomerControllerTest.php`
- **New Tests**: 3 testes para sort, cache

##### `tests/Feature/UserControllerTest.php`
- **New Tests**: 4 testes para search, sort, cache

##### `tests/Feature/SaleControllerTest.php`
- **New Tests**: 3 testes para search (notes), sort

##### `tests/Feature/StoreProductControllerTest.php`
- **New Tests**: 3 testes para search via product, sort por product_name

#### Test Coverage
- **Unit Tests**: 6 novos arquivos de teste, ~20 testes
- **Feature Tests**: ~22 novos testes adicionados aos testes existentes
- **Total**: ~42 novos testes implementados
- **Coverage Target**: 80%+ (conforme Backend.md)

## Performance Considerations

- **Cache TTL**: Configurável via environment variables, defaults otimizados (120s list, 300s show)
- **Cache Keys**: Determinísticos e baseados em hash para eficiência
- **Search**: Usa índices existentes quando disponíveis (name, email, etc.)
- **Sort**: Aplicado via Eloquent orderBy (usa índices quando disponíveis)
- **Product Name Sort**: Join otimizado para store products
- **Cache Invalidation**: Eficiente para Redis (pattern matching), TTL natural para outros drivers

## Integration Points

- **Reutiliza**: Todos os Resources existentes, Form Requests existentes, Policies existentes, Models existentes
- **Compatível com**: Todas as features anteriores (Stores, Products, Store Products, Customers, Sales, Users)
- **Preparado para**: 
  - Cache tags no Redis para invalidação mais eficiente (follow-up)
  - Índices adicionais se profiling mostrar necessidade (follow-up)
  - Monitoramento de cache hit rate (follow-up)

---

# Feature Implementation: Database Seeders

## Overview
- **Feature**: Boss Pods — Database Seeders
- **Implementation Date**: January 24, 2026
- **Files Created**: 7
- **Files Modified**: 3
- **Lines Added**: ~600
- **Lines Removed**: 0

## Files Created

### Seeders

#### `database/seeders/StoreSeeder.php`
- **Purpose**: Seed stores para desenvolvimento/demo
- **Key Components**:
  - Cria 3 stores determinísticos: "Boss Pods Downtown", "Boss Pods Mall", "Boss Pods Airport"
  - Usa `firstOrCreate` para idempotência (baseado em `name`)
  - Executa apenas quando `config('boss_pods.seed.demo')` é true
- **Dependencies**: Nenhuma (stores não têm FKs)

#### `database/seeders/ProductSeeder.php`
- **Purpose**: Seed products (catálogo master) para desenvolvimento/demo
- **Key Components**:
  - Cria 10 products com combinações determinísticas de (brand, name, flavor)
  - Usa `firstOrCreate` para idempotência baseado em `['brand', 'name', 'flavor']`
  - Executa apenas quando `config('boss_pods.seed.demo')` é true
- **Dependencies**: Nenhuma (products não têm FKs)

#### `database/seeders/AdminUserSeeder.php`
- **Purpose**: Cria usuário admin inicial (de env) e opcionalmente usuários demo (manager/seller)
- **Key Components**:
  - `createAdminUser()`: Cria admin de configuração/env
    - Email: `config('boss_pods.seed.admin_email')` ou `env('SEED_ADMIN_EMAIL')`
    - Password: `config('boss_pods.seed.admin_password')` ou `env('SEED_ADMIN_PASSWORD')` (obrigatório em produção)
    - Name: `config('boss_pods.seed.admin_name')` ou `env('SEED_ADMIN_NAME')`
    - Role: `admin`, `store_id`: `null`
    - Idempotente: skip se email já existe
  - `createDemoUsers()`: Cria manager e sellers apenas em non-production
    - Manager: `manager@boss-pods.test`, atribuído à primeira store
    - Seller 1: `seller1@boss-pods.test`, atribuído à primeira store
    - Seller 2: `seller2@boss-pods.test`, atribuído à segunda store (se existir)
    - Password: `config('boss_pods.seed.demo_password')` (default: `password`)
- **Dependencies**: Stores (para demo users com store_id)

#### `database/seeders/StoreProductSeeder.php`
- **Purpose**: Seed store_products (inventário) para stores e products demo
- **Key Components**:
  - Para cada store, anexa ~70% dos products com preços e estoque aleatórios
  - Usa `firstOrCreate` para idempotência baseado em `['store_id', 'product_id']`
  - Garante `sale_price >= cost_price` (ajusta se necessário com markup mínimo de 20%)
  - Executa apenas quando `config('boss_pods.seed.demo')` é true
- **Dependencies**: Stores, Products

#### `database/seeders/CustomerSeeder.php`
- **Purpose**: Seed customers para desenvolvimento/demo
- **Key Components**:
  - Cria 8 customers com dados determinísticos
  - Todos com `total_purchases = 0` (será incrementado por sales se SaleSeeder rodar)
  - Usa `firstOrCreate` para idempotência baseado em `phone`
  - Skip se já existem 5+ customers
  - Executa apenas quando `config('boss_pods.seed.demo')` é true
- **Dependencies**: Nenhuma (customers não têm FKs)

#### `database/seeders/SaleSeeder.php`
- **Purpose**: Seed sales e sale_items para desenvolvimento/demo (opcional)
- **Key Components**:
  - Cria 3-5 sales usando **SaleService::createSale** (respeita regras de negócio)
  - Usa stores, users (manager/seller), customers e store_products seedados
  - Garante que store_products têm estoque suficiente antes de criar sale
  - Cada sale tem 1-3 items com quantidades aleatórias
  - 70% de chance de ter customer associado
  - Executa apenas quando `config('boss_pods.seed.demo')` é true
- **Dependencies**: Stores, Users (manager/seller), Customers, StoreProducts
- **Business Rules**: Usa SaleService para garantir decremento de stock e incremento de total_purchases

### Test Files

#### `tests/Feature/SeederTest.php`
- **Coverage**: Testes para todos os seeders
- **Tests**:
  - `test_store_seeder_creates_stores`: Verifica criação de stores
  - `test_store_seeder_is_idempotent`: Verifica idempotência
  - `test_product_seeder_creates_products`: Verifica criação de products
  - `test_product_seeder_is_idempotent`: Verifica idempotência
  - `test_admin_user_seeder_creates_admin`: Verifica criação de admin
  - `test_admin_user_seeder_is_idempotent`: Verifica idempotência
  - `test_admin_user_seeder_creates_demo_users_when_demo_enabled`: Verifica criação de demo users
  - `test_store_product_seeder_creates_store_products`: Verifica criação e validação de preços
  - `test_store_product_seeder_is_idempotent`: Verifica idempotência
  - `test_customer_seeder_creates_customers`: Verifica criação de customers
  - `test_customer_seeder_sets_total_purchases_to_zero`: Verifica total_purchases inicial
  - `test_sale_seeder_creates_sales_via_service`: Verifica criação via SaleService e decremento de stock
  - `test_database_seeder_runs_all_seeders_in_order`: Verifica orquestração completa
  - `test_database_seeder_only_runs_admin_in_production`: Verifica comportamento em produção

## Files Modified

### Configuration

#### `config/boss_pods.php`
- **Changes**: Adicionada seção `seed` com configurações
- **New Keys**:
  - `seed.admin_email`: Email do admin (default: `admin@boss-pods.test`)
  - `seed.admin_password`: Password do admin (obrigatório em produção)
  - `seed.admin_name`: Nome do admin (default: `Admin`)
  - `seed.demo_password`: Password para demo users (default: `password`)
  - `seed.demo`: Flag para habilitar seeders demo (default: `APP_ENV !== 'production'`)
- **Environment Variables**: `SEED_ADMIN_EMAIL`, `SEED_ADMIN_PASSWORD`, `SEED_ADMIN_NAME`, `SEED_DEMO`, `SEED_DEMO_PASSWORD`

### Factories

#### `database/factories/UserFactory.php`
- **Changes**: Adicionados states `admin()`, `manager(Store $store)`, `seller(Store $store)`
- **New Methods**:
  - `admin()`: Define `role = 'admin'`, `store_id = null`
  - `manager(Store $store)`: Define `role = 'manager'`, `store_id = $store->id`
  - `seller(Store $store)`: Define `role = 'seller'`, `store_id = $store->id`
- **Usage**: Usado por AdminUserSeeder para criar demo users

### Seeders

#### `database/seeders/DatabaseSeeder.php`
- **Changes**: Reescrito para orquestrar todos os seeders em ordem FK-safe
- **Before**: Apenas criava um usuário de teste
- **After**: Orquestra seeders na ordem:
  1. StoreSeeder (dev only)
  2. ProductSeeder (dev only)
  3. AdminUserSeeder (sempre)
  4. StoreProductSeeder (dev only)
  5. CustomerSeeder (dev only)
  6. SaleSeeder (dev only)
- **Environment Logic**:
  - **Production**: Apenas AdminUserSeeder
  - **Non-Production**: Todos os seeders se `SEED_DEMO=true` (default)
  - **Non-Production com SEED_DEMO=false**: Apenas AdminUserSeeder

## Execution Order & Dependencies

```
1. StoreSeeder        (stores — no FKs)
   ↓
2. ProductSeeder      (products — no FKs)
   ↓
3. AdminUserSeeder    (users — FK → stores para demo users; admin tem store_id null)
   ↓
4. StoreProductSeeder (store_products — FK → stores, products)
   ↓
5. CustomerSeeder     (customers — no FKs)
   ↓
6. SaleSeeder         (sales — FK → stores, users, customers, store_products)
```

## Environment Variables

### Required (Production)
- `SEED_ADMIN_PASSWORD`: Password do admin (obrigatório em produção)

### Optional
- `SEED_ADMIN_EMAIL`: Email do admin (default: `admin@boss-pods.test`)
- `SEED_ADMIN_NAME`: Nome do admin (default: `Admin`)
- `SEED_DEMO`: `true`/`false` para forçar habilitar/desabilitar seeders demo (default: `APP_ENV !== 'production'`)
- `SEED_DEMO_PASSWORD`: Password para demo users (default: `password`)

## Implementation Decisions

1. **Idempotency**:
   - Stores: `firstOrCreate` baseado em `name`
   - Products: `firstOrCreate` baseado em `['brand', 'name', 'flavor']`
   - Admin: Skip se email já existe (não sobrescreve)
   - StoreProducts: `firstOrCreate` baseado em `['store_id', 'product_id']`
   - Customers: `firstOrCreate` baseado em `phone` + skip se count >= 5

2. **Production Safety**:
   - Apenas AdminUserSeeder roda em produção
   - `SEED_ADMIN_PASSWORD` obrigatório em produção (warning se faltar)
   - Demo users nunca criados em produção

3. **Business Rules**:
   - SaleSeeder usa `SaleService::createSale` para garantir:
     - Decremento de stock
     - Incremento de `total_purchases` em customers
     - Validação de estoque suficiente
   - StoreProductSeeder garante `sale_price >= cost_price` (ajusta se necessário)

4. **Data Volume**:
   - Stores: 3 stores determinísticos
   - Products: 10 products determinísticos
   - StoreProducts: ~70% dos products por store
   - Customers: 8 customers determinísticos
   - Sales: 3-5 sales com 1-3 items cada

5. **Demo Users**:
   - Manager: `manager@boss-pods.test`
   - Seller 1: `seller1@boss-pods.test`
   - Seller 2: `seller2@boss-pods.test` (se segunda store existir)
   - Password padrão: `password` (documentado, apenas dev)

## Testing

#### Test Files Created

##### `tests/Feature/SeederTest.php`
- **Coverage**: 14 testes cobrindo todos os seeders
- **Tests**:
  - Criação de dados por seeder
  - Idempotência onde aplicável
  - Validação de regras de negócio (preços, total_purchases, stock)
  - Comportamento em produção vs non-production
  - Orquestração completa via DatabaseSeeder

#### Test Coverage
- **Feature Tests**: 14 testes implementados
- **Coverage Target**: 80%+ (conforme Backend.md)

## Usage

### Development
```bash
# Seed completo (todos os seeders)
php artisan db:seed

# Ou com migrate fresh
php artisan migrate:fresh --seed
```

### Production
```bash
# Apenas admin user
php artisan db:seed --class=AdminUserSeeder
```

### Individual Seeders
```bash
php artisan db:seed --class=StoreSeeder
php artisan db:seed --class=ProductSeeder
# etc.
```

## Security Considerations

- **Passwords**: Nunca hardcoded; sempre de env/config
- **Production**: Apenas admin seed; nenhum dado demo
- **Demo Password**: Documentado como `password` (apenas dev)
- **Admin Password**: Obrigatório em produção via `SEED_ADMIN_PASSWORD`

## Integration Points

- **Reutiliza**: Todas as factories existentes, SaleService para criação de sales
- **Compatível com**: Todas as features anteriores (Stores, Products, Store Products, Customers, Sales, Users)
- **Preparado para**: 
  - CI/CD pipelines (seed em testes)
  - Ambientes de staging (seed completo)
  - Produção (apenas admin)

---

# Feature Implementation: Seeders with Real Data

## Overview
- **Feature**: Boss Pods — Seeders with Real Data
- **Implementation Date**: January 23, 2026
- **Files Created**: 1
- **Files Modified**: 4
- **Lines Added**: ~200
- **Lines Removed**: ~150

## Feature Description

Implementação de seeders com dados reais e específicos conforme `Docs/Plan-Seeders-Real-Data.md`:
- 2 lojas reais (Palmas, Guarujá) com endereços brasileiros
- 12 produtos reais de e-cigarette/pod (Vaporesso, SMOK, Uwell, Geekvape)
- 4 usuários: 2 admins, 1 manager (Sayd), 1 seller (Vendedor 1)
- Inventário idêntico para ambas as lojas (12 produtos × 2 lojas = 24 store_products)
- Sem clientes e sem vendas (tabelas vazias para testes limpos)

## Files Created

### `database/seeders/UserSeeder.php`
- **Purpose**: Cria usuários reais conforme especificação do plano
- **Key Components**:
  - Cria 2 admins: `admin1@boss-pods.test` e `admin2@boss-pods.test`
  - Cria 1 manager: `Sayd` (`sayd@boss-pods.test`) vinculado à loja Palmas
  - Cria 1 seller: `Vendedor 1` (`vendedor1@boss-pods.test`) vinculado à loja Guarujá
  - Usa `firstOrCreate` para idempotência baseada em email
  - Senhas vêm de `config('boss_pods.seed.admin_password')` ou `demo_password`
- **Dependencies**: `App\Models\Store`, `App\Models\User`
- **Idempotency**: `firstOrCreate` em `email` garante que re-execução não cria duplicatas

## Files Modified

### `database/seeders/StoreSeeder.php`
- **Changes**: Substituído dados genéricos por dados reais brasileiros
- **Before**: 3 lojas genéricas (Boss Pods Downtown, Mall, Airport)
- **After**: 2 lojas reais:
  - **Palmas**: Avenida Beira Mar, 1234 - Centro, Guarujá - SP, 11400-000, (13) 3355-1234
  - **Guarujá**: Rua das Flores, 567 - Praia da Enseada, Guarujá - SP, 11400-000, (13) 3355-5678
- **Impact**: Dados mais realistas para desenvolvimento e testes

### `database/seeders/ProductSeeder.php`
- **Changes**: Substituído produtos genéricos por produtos reais de e-cigarette/pod
- **Before**: 10 produtos genéricos (VapePro, CloudMax, PuffElite)
- **After**: 12 produtos reais:
  - **Vaporesso XROS 3**: Mint, Tobacco, Fruit (3 produtos)
  - **SMOK Nord 5**: Mint, Tobacco, Vanilla (3 produtos)
  - **Uwell Caliburn G3**: Mint, Fruit, Coffee (3 produtos)
  - **Geekvape Aegis Pod**: Mint, Tobacco, Fruit (3 produtos)
- **Impact**: Produtos reais facilitam testes mais realistas

### `database/seeders/StoreProductSeeder.php`
- **Changes**: Substituído seleção aleatória por inventário idêntico e determinístico
- **Before**: 
  - Selecionava 70% dos produtos aleatoriamente por loja
  - Valores aleatórios (fake) para preços e estoque
  - Ajuste posterior para garantir `sale_price >= cost_price`
- **After**:
  - **Todas as 12 lojas** são adicionadas a **ambas as lojas** (Palmas e Guarujá)
  - Valores exatos e idênticos para ambas as lojas:
    - Vaporesso XROS 3: cost_price 25.00, sale_price 45.00, stock 50, min_stock 10
    - SMOK Nord 5: cost_price 30.00, sale_price 55.00, stock 40, min_stock 8
    - Uwell Caliburn G3: cost_price 28.00, sale_price 50.00, stock 45, min_stock 9
    - Geekvape Aegis Pod: cost_price 22.00, sale_price 40.00, stock 60, min_stock 12
  - Total: 24 store_products (12 × 2 lojas)
- **Impact**: 
  - Inventário idêntico permite testes previsíveis
  - Valores determinísticos garantem consistência entre execuções
  - Facilita testes de vendas e gerenciamento de estoque

### `database/seeders/DatabaseSeeder.php`
- **Changes**: Atualizado para usar `UserSeeder` e pular `CustomerSeeder` e `SaleSeeder`
- **Before**: 
  - Chamava `AdminUserSeeder` (que criava admin + demo users)
  - Incluía `CustomerSeeder` e `SaleSeeder` em ambientes não-produção
- **After**:
  - Chama `UserSeeder` (que cria 2 admins, 1 manager, 1 seller)
  - **Pula** `CustomerSeeder` e `SaleSeeder` (conforme plano)
  - Ordem de execução: StoreSeeder → ProductSeeder → UserSeeder → StoreProductSeeder
- **Impact**: 
  - Tabelas `customers` e `sales` ficam vazias para testes limpos
  - Usuários criados conforme especificação exata do plano

## Database Changes

### Seeders Execution Order
1. **StoreSeeder**: Cria Palmas e Guarujá
2. **ProductSeeder**: Cria 12 produtos reais
3. **UserSeeder**: Cria 2 admins, 1 manager (Sayd), 1 seller
4. **StoreProductSeeder**: Cria inventário idêntico (12 produtos × 2 lojas = 24 records)
5. **CustomerSeeder**: **SKIP** (não executado)
6. **SaleSeeder**: **SKIP** (não executado)

### Data Seeded

#### Stores (2)
- Palmas: Avenida Beira Mar, 1234 - Centro, Guarujá - SP, 11400-000, (13) 3355-1234
- Guarujá: Rua das Flores, 567 - Praia da Enseada, Guarujá - SP, 11400-000, (13) 3355-5678

#### Products (12)
- Vaporesso XROS 3: Mint, Tobacco, Fruit
- SMOK Nord 5: Mint, Tobacco, Vanilla
- Uwell Caliburn G3: Mint, Fruit, Coffee
- Geekvape Aegis Pod: Mint, Tobacco, Fruit

#### Users (4)
- Admin 1: `admin1@boss-pods.test` (role: admin, store_id: null)
- Admin 2: `admin2@boss-pods.test` (role: admin, store_id: null)
- Sayd: `sayd@boss-pods.test` (role: manager, store_id: Palmas)
- Vendedor 1: `vendedor1@boss-pods.test` (role: seller, store_id: Guarujá)

#### Store Products (24)
- 12 produtos × 2 lojas = 24 store_products
- Inventário idêntico para ambas as lojas (mesmos valores de custo, venda, estoque)

#### Customers (0)
- Tabela vazia (não seedada)

#### Sales (0)
- Tabela vazia (não seedada)

## Configuration

### `config/boss_pods.php`
- **Status**: Sem mudanças necessárias
- **Configurações existentes**:
  - `seed.admin_password`: Senha para admins (env `SEED_ADMIN_PASSWORD`)
  - `seed.demo_password`: Senha para manager/seller (env `SEED_DEMO_PASSWORD`, default: `password`)

## Testing Strategy

### Manual Verification
Após `php artisan migrate:fresh --seed`:
- ✅ Verificar 2 stores: Palmas, Guarujá
- ✅ Verificar 12 products (Vaporesso, SMOK, Uwell, Geekvape)
- ✅ Verificar 4 users: 2 admin, 1 manager (Sayd), 1 seller
- ✅ Verificar 24 store_products (12 por loja, estoque idêntico)
- ✅ Verificar 0 customers
- ✅ Verificar 0 sales

### Login Tests
- Login como `admin1@boss-pods.test` / `admin2@boss-pods.test`
- Login como `sayd@boss-pods.test` (manager, loja Palmas)
- Login como `vendedor1@boss-pods.test` (seller, loja Guarujá)

### Inventory Tests
- Listar store products para Palmas → deve retornar 12 produtos com estoque 50/40/45/60
- Listar store products para Guarujá → deve retornar 12 produtos com **mesmo estoque** que Palmas

## Security Considerations

- **Passwords**: Nunca hardcoded; sempre de env/config
- **Production**: Seeders não executam em produção (retornam early se `config('boss_pods.seed.demo')` false)
- **Idempotency**: Todos os seeders usam `firstOrCreate` para evitar duplicatas
- **Password Hashing**: Automático via model cast (`'password' => 'hashed'`)

## Integration Points

- **Reutiliza**: Modelos existentes (Store, Product, User, StoreProduct)
- **Compatível com**: Todas as features anteriores
- **Preparado para**: 
  - Testes com dados consistentes e previsíveis
  - Desenvolvimento com dados realistas
  - Ambientes de staging com dados determinísticos

## Notes

### Implementation Decisions
- **UserSeeder separado**: Criado novo seeder ao invés de modificar `AdminUserSeeder` para manter compatibilidade
- **Inventário idêntico**: Ambas as lojas têm exatamente os mesmos produtos e estoques para testes previsíveis
- **Dados reais**: Uso de marcas e modelos reais de e-cigarette/pod para maior realismo
- **Sem customers/sales**: Tabelas vazias permitem testes limpos de criação de vendas e clientes

### Known Limitations
- Nenhuma limitação conhecida

### Performance Considerations
- Seeders usam `firstOrCreate` para eficiência e idempotência
- Busca de stores por nome (`where('name', 'Palmas')`) é eficiente com índice em `name`
