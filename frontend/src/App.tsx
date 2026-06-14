import { FormEvent, MouseEvent, useEffect, useMemo, useRef, useState } from 'react'
import './App.css'

type Product = {
  id: number
  name: string
  category: string
  description: string
  image_url: string
  price: number
  rank: number
  score: number
  source: string
  inventory_count: number
  vendor: Vendor
}

type Vendor = {
  id: number
  name: string
  slug: string
  description: string
  logo_url: string
  banner_url: string
  location: string
  is_verified: boolean
  rating: number
  fulfillment_days: number
  products_count?: number
}

type SearchResponse = {
  search_id: number
  search_type: string
  result_count: number
  results: Product[]
}

type Summary = {
  total_searches: number
  zero_result_rate: number
  abandonment_rate: number
  click_through_rate: number
}
type CommerceSummary = { orders: number; gmv: number; average_order_value: number; items_sold: number; active_inventory: number }

type Gap = { category: string; clicks: number }
type ZeroResult = { id: number; query_text: string | null; search_type: string; created_at: string }
type VendorPerformance = Vendor & { appearances: number; clicks: number; click_through_rate: number }
type CartItem = { product: Product; quantity: number }
type OrderResponse = {
  reference: string
  status: string
  payment_status: string
  total: number
  items: { id: number; product_name: string; vendor_name: string; quantity: number; line_total: number }[]
}

const suggestions = [
  'Owambe outfit that is classy but not too loud',
  'Durable school bag for my pikin',
  'Heavy-duty power solution for home',
]

const sessionId = crypto.randomUUID()
const money = new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN', maximumFractionDigits: 0 })

function App() {
  const [view, setView] = useState<'search' | 'analytics'>('search')
  const [query, setQuery] = useState('')
  const [image, setImage] = useState<File | null>(null)
  const [response, setResponse] = useState<SearchResponse | null>(null)
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState('')
  const [summary, setSummary] = useState<Summary | null>(null)
  const [commerceSummary, setCommerceSummary] = useState<CommerceSummary | null>(null)
  const [gaps, setGaps] = useState<Gap[]>([])
  const [zeroResults, setZeroResults] = useState<ZeroResult[]>([])
  const [vendors, setVendors] = useState<Vendor[]>([])
  const [selectedVendor, setSelectedVendor] = useState<Vendor | null>(null)
  const [vendorPerformance, setVendorPerformance] = useState<VendorPerformance[]>([])
  const [activeProduct, setActiveProduct] = useState(0)
  const [savedProducts, setSavedProducts] = useState<Set<number>>(new Set())
  const [cart, setCart] = useState<CartItem[]>(() => {
    try {
      return JSON.parse(localStorage.getItem('konnectfind-cart') ?? '[]')
    } catch {
      return []
    }
  })
  const [commercePanel, setCommercePanel] = useState<'product' | 'cart' | 'checkout' | null>(null)
  const [selectedProduct, setSelectedProduct] = useState<Product | null>(null)
  const [checkoutLoading, setCheckoutLoading] = useState(false)
  const [checkoutError, setCheckoutError] = useState('')
  const [order, setOrder] = useState<OrderResponse | null>(null)
  const feedRef = useRef<HTMLDivElement>(null)

  const imagePreview = useMemo(() => image ? URL.createObjectURL(image) : '', [image])
  const hasResults = Boolean(response?.results.length)
  const cartCount = cart.reduce((total, item) => total + item.quantity, 0)
  const cartSubtotal = cart.reduce((total, item) => total + item.product.price * item.quantity, 0)

  useEffect(() => {
    if (view === 'analytics') loadAnalytics()
  }, [view])

  useEffect(() => {
    fetch('/api/vendors').then((result) => result.json()).then(setVendors)
  }, [])

  useEffect(() => {
    localStorage.setItem('konnectfind-cart', JSON.stringify(cart))
  }, [cart])

  async function search(event?: FormEvent, queryOverride?: string) {
    event?.preventDefault()
    const submittedQuery = queryOverride ?? query
    if (!submittedQuery.trim() && !image) return
    setLoading(true)
    setError('')
    const body = new FormData()
    if (submittedQuery.trim()) body.append('query', submittedQuery.trim())
    if (image) body.append('image', image)
    body.append('session_id', sessionId)
    if (selectedVendor) body.append('vendor_id', String(selectedVendor.id))

    try {
      const result = await fetch('/api/search', { method: 'POST', body })
      if (!result.ok) throw new Error('Search could not be completed.')
      setResponse(await result.json())
      setActiveProduct(0)
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : 'Search could not be completed.')
    } finally {
      setLoading(false)
    }
  }

  async function recordClick(product: Product) {
    if (!response) return
    await fetch('/api/search/click', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ search_id: response.search_id, product_id: product.id, rank: product.rank }),
    })
  }

  function viewProduct(product: Product) {
    recordClick(product)
    setSelectedProduct(product)
    setCommercePanel('product')
  }

  function addToCart(product: Product, quantity = 1) {
    setCart((current) => {
      const existing = current.find((item) => item.product.id === product.id)
      if (existing) {
        return current.map((item) => item.product.id === product.id
          ? { ...item, quantity: Math.min(product.inventory_count, item.quantity + quantity) }
          : item)
      }
      return [...current, { product, quantity: Math.min(product.inventory_count, quantity) }]
    })
    setCommercePanel('cart')
  }

  function updateCartQuantity(productId: number, quantity: number) {
    setCart((current) => current
      .map((item) => item.product.id === productId
        ? { ...item, quantity: Math.min(item.product.inventory_count, Math.max(0, quantity)) }
        : item)
      .filter((item) => item.quantity > 0))
  }

  async function checkout(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setCheckoutLoading(true)
    setCheckoutError('')
    const data = Object.fromEntries(new FormData(event.currentTarget))
    try {
      const result = await fetch('/api/orders', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          ...data,
          items: cart.map((item) => ({ product_id: item.product.id, quantity: item.quantity })),
        }),
      })
      const payload = await result.json()
      if (!result.ok) throw new Error(payload.message ?? Object.values(payload.errors ?? {}).flat()[0] ?? 'Checkout could not be completed.')
      setOrder(payload)
      setCart([])
    } catch (reason) {
      setCheckoutError(reason instanceof Error ? reason.message : 'Checkout could not be completed.')
    } finally {
      setCheckoutLoading(false)
    }
  }

  function toggleSaved(event: MouseEvent, productId: number) {
    event.stopPropagation()
    setSavedProducts((current) => {
      const next = new Set(current)
      if (next.has(productId)) next.delete(productId)
      else next.add(productId)
      return next
    })
  }

  function refineSearch() {
    setResponse(null)
    setActiveProduct(0)
    requestAnimationFrame(() => window.scrollTo({ top: 0, behavior: 'smooth' }))
  }

  function updateActiveProduct() {
    const feed = feedRef.current
    if (!feed) return
    setActiveProduct(Math.round(feed.scrollTop / feed.clientHeight))
  }

  async function loadAnalytics() {
    const [summaryResponse, gapsResponse, zerosResponse, vendorsResponse, commerceResponse] = await Promise.all([
      fetch('/api/analytics/summary'),
      fetch('/api/analytics/category-gaps'),
      fetch('/api/analytics/zero-results'),
      fetch('/api/analytics/vendor-performance'),
      fetch('/api/analytics/commerce-summary'),
    ])
    setSummary(await summaryResponse.json())
    setGaps(await gapsResponse.json())
    setZeroResults(await zerosResponse.json())
    setVendorPerformance(await vendorsResponse.json())
    setCommerceSummary(await commerceResponse.json())
  }

  return (
    <div className={`shell ${hasResults && view === 'search' ? 'mobile-feed-active' : ''}`}>
      <header>
        <button className="brand" onClick={() => setView('search')}>
          <span className="brand-mark">K</span>
          <span>Konnect<span>Find</span></span>
        </button>
        <nav>
          <button className={view === 'search' ? 'active' : ''} onClick={() => setView('search')}>Product search</button>
          <button className={view === 'analytics' ? 'active' : ''} onClick={() => setView('analytics')}>Discovery analytics</button>
        </nav>
        <div className="header-actions">
          <span className="status"><i /> Search system online</span>
          <button className="cart-trigger" onClick={() => setCommercePanel('cart')} aria-label={`Open cart with ${cartCount} items`}><BagIcon /><span>Cart</span>{cartCount > 0 && <b>{cartCount}</b>}</button>
        </div>
      </header>

      {view === 'search' ? (
        <main className={`search-main ${hasResults ? 'has-results' : ''}`}>
          <section className="hero">
            <div className="hero-layout">
              <div className="hero-copy">
                <div className="eyebrow">Built for how Nigerians actually shop</div>
                <h1>Describe it. Show it.<br /><em>Find it.</em></h1>
                <p>Search naturally with words, upload a picture, or combine both. No official product name required.</p>
                <div className="market-stats">
                  <span><strong>{vendors.reduce((total, vendor) => total + (vendor.products_count ?? 0), 0)}+</strong><small>products to discover</small></span>
                  <span><strong>{vendors.length}</strong><small>independent stores</small></span>
                  <span><strong>1 search</strong><small>across the marketplace</small></span>
                </div>
              </div>
              <div className="hero-showcase" aria-hidden="true">
                <span className="showcase-orbit orbit-one">Owambe</span>
                <span className="showcase-orbit orbit-two">Home power</span>
                <span className="showcase-orbit orbit-three">Everyday finds</span>
                <div className="showcase-card">
                  <span>Search with your own words</span>
                  <strong>“Something classy,<br />not too loud.”</strong>
                  <i><SearchIcon /> Meaning-first discovery</i>
                </div>
              </div>
            </div>
            <div className="discovery-dock">
              <form className="search-box" onSubmit={search}>
                <div className="search-row">
                  <span className="search-icon"><SearchIcon /></span>
                  <input value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Try “something classy for an owambe…”" />
                  <label className="upload-button" title="Add an image">
                    <input type="file" accept="image/*" onChange={(event) => setImage(event.target.files?.[0] ?? null)} />
                    <span>＋</span> Add image
                  </label>
                  <button className="primary" disabled={loading}>{loading ? 'Finding…' : 'Find products'} <ArrowIcon /></button>
                </div>
                {image && <div className="image-chip"><img src={imagePreview} alt="" /><span>{image.name}</span><button type="button" onClick={() => setImage(null)}>×</button></div>}
              </form>
              <div className="suggestions">
                <span>Popular searches</span>
                {suggestions.map((suggestion) => <button key={suggestion} onClick={() => { setQuery(suggestion); search(undefined, suggestion) }}>{suggestion}</button>)}
              </div>
              <div className="storefronts">
                <div className="storefronts-heading"><span>Shop trusted storefronts</span><small>{vendors.length} independent vendors</small></div>
                <div className="storefront-list">
                  <button className={!selectedVendor ? 'selected' : ''} onClick={() => setSelectedVendor(null)}>
                    <span className="store-avatar marketplace-avatar">K</span>
                    <span><b>All vendors</b><small>Marketplace-wide search</small></span>
                  </button>
                  {vendors.map((vendor) => (
                    <button key={vendor.id} className={selectedVendor?.id === vendor.id ? 'selected' : ''} onClick={() => setSelectedVendor(vendor)}>
                      <VendorAvatar vendor={vendor} />
                      <span><b>{vendor.name}{vendor.is_verified && <VerifiedIcon />}</b><small>{vendor.location} · {vendor.products_count} products</small></span>
                    </button>
                  ))}
                </div>
              </div>
            </div>
          </section>

          <section className="results-section">
            <div className="section-heading">
              <div>
                <span className="eyebrow">{response ? `${selectedVendor?.name ?? 'Marketplace'} · ${response.search_type} search` : 'Curated marketplace'}</span>
                <h2>{response ? `${response.result_count} relevant products` : 'Ready when you are'}</h2>
              </div>
              {response && <div className="result-note"><span>Ranked by meaning and visual similarity</span><button onClick={refineSearch}>Refine search</button></div>}
            </div>
            {error && <div className="notice error">{error}</div>}
            {response?.result_count === 0 && <div className="notice"><b>No close matches yet.</b> This search has been logged so the catalogue team can close the discovery gap.</div>}
            {hasResults && (
              <div className="mobile-feed-topbar">
                <button className="mobile-feed-brand" onClick={refineSearch} aria-label="Return to search">
                  <span className="brand-mark">K</span>
                </button>
                <button className="mobile-query" onClick={refineSearch}>
                  <SearchIcon />
                  <span>{query || 'Image search'}</span>
                </button>
                <button className="mobile-cart-trigger" onClick={() => setCommercePanel('cart')} aria-label={`Open cart with ${cartCount} items`}><BagIcon />{cartCount > 0 && <b>{cartCount}</b>}</button>
                <span className="mobile-feed-count">{activeProduct + 1}/{response?.result_count}</span>
              </div>
            )}
            <div className="product-grid" ref={feedRef} onScroll={updateActiveProduct}>
              {response?.results.map((product) => (
                <article className="product-card" key={product.id} onClick={() => viewProduct(product)}>
                  <div className="product-image">
                    <img src={product.image_url} alt={product.name} />
                    <span>#{product.rank}</span>
                    <div className="mobile-image-shade" />
                  </div>
                  <div className="product-body">
                    <div className="vendor-line"><VendorAvatar vendor={product.vendor} /><span><b>{product.vendor.name}{product.vendor.is_verified && <VerifiedIcon />}</b><small>{product.vendor.location} · Ships in {product.vendor.fulfillment_days} days</small></span></div>
                    <div className="product-meta"><span>{product.category}</span><small>{Math.round(product.score * 100)}% match</small></div>
                    <h3>{product.name}</h3>
                    <p>{product.description}</p>
                    <strong>{money.format(product.price)}</strong>
                    <div className="desktop-card-actions">
                      <button className="desktop-view-button" onClick={(event) => { event.stopPropagation(); viewProduct(product) }}>View product <ArrowIcon /></button>
                      <button className={savedProducts.has(product.id) ? 'desktop-save-button saved' : 'desktop-save-button'} onClick={(event) => toggleSaved(event, product.id)} aria-label="Save product"><HeartIcon filled={savedProducts.has(product.id)} /></button>
                    </div>
                    <button className="mobile-shop-button" onClick={(event) => { event.stopPropagation(); viewProduct(product) }}>View product <ArrowIcon /></button>
                  </div>
                  <div className="mobile-action-rail">
                    <button className="mobile-vendor-avatar" onClick={(event) => { event.stopPropagation(); setSelectedVendor(product.vendor); refineSearch() }} aria-label={`Open ${product.vendor.name} storefront`}><VendorAvatar vendor={product.vendor} /><small>Store</small></button>
                    <button className={savedProducts.has(product.id) ? 'saved' : ''} onClick={(event) => toggleSaved(event, product.id)} aria-label="Save product">
                      <HeartIcon filled={savedProducts.has(product.id)} />
                      <small>{savedProducts.has(product.id) ? 'Saved' : 'Save'}</small>
                    </button>
                    <button onClick={(event) => { event.stopPropagation(); viewProduct(product) }} aria-label="View product details">
                      <BagIcon />
                      <small>View</small>
                    </button>
                  </div>
                  {product.rank === 1 && <div className="swipe-cue"><ChevronIcon /><span>Swipe for more</span></div>}
                </article>
              ))}
            </div>
            {hasResults && (
              <div className="mobile-feed-progress" aria-hidden="true">
                {response?.results.map((product, index) => <i key={product.id} className={index === activeProduct ? 'active' : ''} />)}
              </div>
            )}
          </section>
        </main>
      ) : (
        <main className="analytics">
          <section className="analytics-heading">
            <div><span className="eyebrow">Search intelligence</span><h1>Discovery health</h1><p>See what shoppers find, where they leave, and which catalogue gaps deserve attention.</p></div>
            <button className="primary" onClick={loadAnalytics}>Refresh data</button>
          </section>
          <section className="metric-grid">
            <Metric label="Total searches" value={String(summary?.total_searches ?? 0)} detail="All query types" />
            <Metric label="Click-through rate" value={`${summary?.click_through_rate ?? 0}%`} detail="Searches that led to a product" />
            <Metric label="Zero-result rate" value={`${summary?.zero_result_rate ?? 0}%`} detail="Unserved shopper intent" />
            <Metric label="Abandonment rate" value={`${summary?.abandonment_rate ?? 0}%`} detail="Searches without a click" />
          </section>
          <section className="commerce-metric-grid">
            <Metric label="Orders placed" value={String(commerceSummary?.orders ?? 0)} detail="Validated marketplace orders" />
            <Metric label="Gross merchandise value" value={money.format(commerceSummary?.gmv ?? 0)} detail="Total value including delivery" />
            <Metric label="Average order value" value={money.format(commerceSummary?.average_order_value ?? 0)} detail="Mean value per completed order" />
            <Metric label="Items sold" value={String(commerceSummary?.items_sold ?? 0)} detail={`${commerceSummary?.active_inventory ?? 0} units remain active`} />
          </section>
          <section className="analytics-grid">
            <div className="panel"><div className="panel-title"><span>Category discovery gaps</span><small>Fewer clicks means less discovery</small></div>
              <div className="bars">{gaps.map((gap) => <div className="bar-row" key={gap.category}><span>{gap.category}</span><div><i style={{ width: `${Math.max(4, gap.clicks * 18)}%` }} /></div><b>{gap.clicks}</b></div>)}</div>
            </div>
            <div className="panel"><div className="panel-title"><span>Latest zero-result queries</span><small>Direct catalogue opportunities</small></div>
              <div className="zero-list">{zeroResults.length ? zeroResults.map((item) => <div key={item.id}><span>{item.query_text || 'Image-only query'}</span><small>{item.search_type}</small></div>) : <p>No zero-result queries yet. That is a lovely start.</p>}</div>
            </div>
            <div className="panel vendor-performance-panel"><div className="panel-title"><span>Vendor discovery performance</span><small>Marketplace seller visibility</small></div>
              <div className="vendor-performance">{vendorPerformance.map((vendor) => <div key={vendor.id}><VendorAvatar vendor={vendor} /><span><b>{vendor.name}{vendor.is_verified && <VerifiedIcon />}</b><small>{vendor.products_count} products · {vendor.appearances} appearances</small></span><strong>{vendor.click_through_rate}%<small>CTR</small></strong></div>)}</div>
            </div>
          </section>
        </main>
      )}
      {commercePanel && (
        <div className="commerce-overlay" onClick={() => setCommercePanel(null)}>
          <aside className={`commerce-panel ${commercePanel === 'product' ? 'product-panel' : ''}`} onClick={(event) => event.stopPropagation()}>
            <button className="panel-close" onClick={() => setCommercePanel(null)} aria-label="Close">×</button>
            {commercePanel === 'product' && selectedProduct && (
              <ProductDetails product={selectedProduct} saved={savedProducts.has(selectedProduct.id)} onSave={(event) => toggleSaved(event, selectedProduct.id)} onAdd={() => addToCart(selectedProduct)} />
            )}
            {commercePanel === 'cart' && (
              <CartPanel cart={cart} subtotal={cartSubtotal} onQuantity={updateCartQuantity} onCheckout={() => { setOrder(null); setCommercePanel('checkout') }} onContinue={() => setCommercePanel(null)} />
            )}
            {commercePanel === 'checkout' && (
              order
                ? <OrderConfirmation order={order} onDone={() => { setOrder(null); setCommercePanel(null) }} />
                : <CheckoutPanel cart={cart} subtotal={cartSubtotal} loading={checkoutLoading} error={checkoutError} onSubmit={checkout} onBack={() => setCommercePanel('cart')} />
            )}
          </aside>
        </div>
      )}
    </div>
  )
}

function ProductDetails({ product, saved, onSave, onAdd }: { product: Product; saved: boolean; onSave: (event: MouseEvent) => void; onAdd: () => void }) {
  return <div className="product-details">
    <div className="detail-image"><img src={product.image_url} alt={product.name} /><span>{product.category}</span></div>
    <div className="detail-content">
      <div className="vendor-line"><VendorAvatar vendor={product.vendor} /><span><b>{product.vendor.name}{product.vendor.is_verified && <VerifiedIcon />}</b><small>{product.vendor.location} · Ships in {product.vendor.fulfillment_days} days · {product.vendor.rating} rating</small></span></div>
      <h2>{product.name}</h2>
      <p>{product.description}</p>
      <div className="stock-line"><i /> {product.inventory_count > 5 ? 'In stock' : `Only ${product.inventory_count} left`} <span>Seller SKU protected</span></div>
      <strong>{money.format(product.price)}</strong>
      <div className="detail-actions">
        <button className="primary" onClick={onAdd} disabled={product.inventory_count < 1}><BagIcon /> Add to cart</button>
        <button className={saved ? 'detail-save saved' : 'detail-save'} onClick={onSave}><HeartIcon filled={saved} /> {saved ? 'Saved' : 'Save'}</button>
      </div>
      <div className="commerce-assurances"><span>✓ Secure checkout</span><span>✓ Stock verified at payment</span><span>✓ Multi-vendor delivery tracking</span></div>
    </div>
  </div>
}

function CartPanel({ cart, subtotal, onQuantity, onCheckout, onContinue }: { cart: CartItem[]; subtotal: number; onQuantity: (id: number, quantity: number) => void; onCheckout: () => void; onContinue: () => void }) {
  return <div className="cart-panel-content">
    <div className="panel-heading"><span className="eyebrow">Your marketplace basket</span><h2>{cart.length ? `${cart.reduce((total, item) => total + item.quantity, 0)} items` : 'Your cart is empty'}</h2></div>
    <div className="cart-items">
      {cart.map(({ product, quantity }) => <div className="cart-item" key={product.id}>
        <img src={product.image_url} alt={product.name} />
        <div><small>{product.vendor.name}</small><b>{product.name}</b><strong>{money.format(product.price)}</strong><span>{product.inventory_count} available</span></div>
        <div className="quantity-control"><button onClick={() => onQuantity(product.id, quantity - 1)}>−</button><span>{quantity}</span><button disabled={quantity >= product.inventory_count} onClick={() => onQuantity(product.id, quantity + 1)}>+</button></div>
      </div>)}
    </div>
    {cart.length > 0 ? <div className="cart-summary"><span>Subtotal <strong>{money.format(subtotal)}</strong></span><small>Delivery is calculated at checkout. Prices and stock are verified when your order is placed.</small><button className="primary" onClick={onCheckout}>Continue to checkout <ArrowIcon /></button></div> : <button className="primary" onClick={onContinue}>Continue shopping</button>}
  </div>
}

function CheckoutPanel({ cart, subtotal, loading, error, onSubmit, onBack }: { cart: CartItem[]; subtotal: number; loading: boolean; error: string; onSubmit: (event: FormEvent<HTMLFormElement>) => void; onBack: () => void }) {
  return <div className="checkout-panel">
    <button className="back-button" onClick={onBack}>← Back to cart</button>
    <div className="panel-heading"><span className="eyebrow">Secure checkout</span><h2>Where should we deliver?</h2><p>{cart.length} product lines · {money.format(subtotal)} subtotal</p></div>
    {error && <div className="notice error">{error}</div>}
    <form onSubmit={onSubmit}>
      <label>Full name<input name="customer_name" required autoComplete="name" /></label>
      <label>Email address<input name="customer_email" type="email" required autoComplete="email" /></label>
      <label>Phone number<input name="customer_phone" required autoComplete="tel" /></label>
      <label className="full-field">Delivery address<textarea name="delivery_address" required rows={3} /></label>
      <label>City<input name="delivery_city" required /></label>
      <label>State<input name="delivery_state" required /></label>
      <fieldset className="full-field"><legend>Payment method</legend><label><input type="radio" name="payment_method" value="pay_on_delivery" defaultChecked /> Pay on delivery</label><label><input type="radio" name="payment_method" value="bank_transfer" /> Bank transfer</label></fieldset>
      <button className="primary full-field" disabled={loading || !cart.length}>{loading ? 'Placing order…' : 'Place order'} <ArrowIcon /></button>
    </form>
  </div>
}

function OrderConfirmation({ order, onDone }: { order: OrderResponse; onDone: () => void }) {
  return <div className="order-confirmation"><span className="confirmation-mark">✓</span><span className="eyebrow">Order successfully placed</span><h2>Thank you. We have it from here.</h2><p>Your order reference is <b>{order.reference}</b>. Payment is {order.payment_status}; vendors can now begin preparing your items.</p><div className="confirmation-total"><span>Order total</span><strong>{money.format(order.total)}</strong></div><button className="primary" onClick={onDone}>Continue shopping</button></div>
}

function Metric({ label, value, detail }: { label: string; value: string; detail: string }) {
  return <article className="metric"><span>{label}</span><strong>{value}</strong><small>{detail}</small></article>
}

function VendorAvatar({ vendor }: { vendor: Vendor }) {
  return <span className="store-avatar">{vendor.logo_url}</span>
}

function VerifiedIcon() {
  return <svg className="verified-icon" viewBox="0 0 24 24" aria-label="Verified vendor"><path d="m12 2 2.1 2.2 3-.4.8 2.9 2.8 1.3-1.3 2.8.4 3-2.9.8-2.1 2.2-2.1-2.2-3 .4-.8-2.9-2.8-1.3 1.3-2.8-.4-3 2.9-.8L12 2Z" /><path d="m8.5 12 2.2 2.2 4.8-4.8" /></svg>
}

function SearchIcon() {
  return <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="6" /><path d="m16 16 4 4" /></svg>
}

function HeartIcon({ filled }: { filled: boolean }) {
  return <svg viewBox="0 0 24 24" aria-hidden="true" className={filled ? 'filled' : ''}><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8l1.1 1.1L12 21l7.8-7.5 1.1-1.1a5.5 5.5 0 0 0-.1-7.8Z" /></svg>
}

function BagIcon() {
  return <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 8h12l1 13H5L6 8Z" /><path d="M9 9V6a3 3 0 0 1 6 0v3" /></svg>
}

function ArrowIcon() {
  return <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M14 7l5 5-5 5" /></svg>
}

function ChevronIcon() {
  return <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m7 14 5-5 5 5" /></svg>
}

export default App
